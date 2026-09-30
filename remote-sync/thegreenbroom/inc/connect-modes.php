<?php
/**
 * Cleaners' payout accounts, kept separately for Stripe test and live.
 *
 * Stripe keeps test and live mode completely apart: a Connect account made
 * in test does not exist in live. The site stored each cleaner's account in
 * one set of fields regardless of mode, so on switching to live:
 *
 *   - "Start Setup" found the test account id already saved and tried to
 *     reuse it, and Stripe answered that there is no such account;
 *   - every cleaner who had finished setup in test still showed as able to
 *     take bookings, and a customer's live payment would have been sent to
 *     an account that does not exist, failing at checkout.
 *
 * Now each of those fields is stored per mode, and whichever mode the site is
 * in is the one read and written. Nothing else had to change: every existing
 * get_post_meta / update_post_meta on these keys for a cleaner is routed here
 * by the metadata filters below. Switching to live gives every cleaner a
 * clean slate - "Start Setup" makes a real live account - and switching back
 * to test finds their test account exactly where it was.
 *
 * The cleaner's own choice of payout schedule is shared, not per mode: it is
 * their preference, not a property of an account.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_connect_mode_keys() {
    return array(
        '_gb_stripe_account_id',
        '_gb_stripe_charges_enabled',
        '_gb_stripe_payouts_enabled',
        '_gb_stripe_details_submitted',
        '_gb_stripe_connect_status',
        '_gb_stripe_requirements_due_count',
        '_gb_stripe_requirements_due_json',
        '_gb_stripe_last_error',
        '_gb_stripe_last_sync',
        '_gb_stripe_country',
    );
}

/**
 * The site's Stripe mode, read directly so this can never loop back into
 * itself (the setting lives on a page, not a cleaner, so it is not routed).
 */
function gb_connect_current_mode() {
    $mode = (string) get_post_meta( (int) get_option( 'page_on_front' ), '_gb_stripe_mode', true );

    return $mode === 'live' ? 'live' : 'test';
}

function gb_connect_mode_key( $key, $mode = null ) {
    return $key . '__' . ( $mode === null ? gb_connect_current_mode() : $mode );
}

function gb_connect_is_routed( $object_id, $meta_key ) {
    return is_string( $meta_key )
        && in_array( $meta_key, gb_connect_mode_keys(), true )
        && get_post_type( (int) $object_id ) === 'gb_cleaner';
}

add_filter( 'get_post_metadata', function ( $value, $object_id, $meta_key, $single ) {
    if ( $value !== null || ! gb_connect_is_routed( $object_id, $meta_key ) ) {
        return $value;
    }

    $stored = get_post_meta( $object_id, gb_connect_mode_key( $meta_key ), false );

    if ( $single ) {
        return array( $stored ? $stored[0] : '' );
    }

    return $stored;
}, 10, 4 );

add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
    if ( $check !== null || ! gb_connect_is_routed( $object_id, $meta_key ) ) {
        return $check;
    }

    // wp_slash: update_post_meta unslashes, and the value arrived unslashed.
    return (bool) update_post_meta( $object_id, gb_connect_mode_key( $meta_key ), wp_slash( $meta_value ), $prev_value );
}, 10, 5 );

add_filter( 'add_post_metadata', function ( $check, $object_id, $meta_key, $meta_value, $unique ) {
    if ( $check !== null || ! gb_connect_is_routed( $object_id, $meta_key ) ) {
        return $check;
    }

    return (bool) add_post_meta( $object_id, gb_connect_mode_key( $meta_key ), wp_slash( $meta_value ), $unique );
}, 10, 5 );

add_filter( 'delete_post_metadata', function ( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
    if ( $check !== null || ! gb_connect_is_routed( $object_id, $meta_key ) ) {
        return $check;
    }

    return (bool) delete_post_meta( $object_id, gb_connect_mode_key( $meta_key ), $meta_value );
}, 10, 5 );

/**
 * One-time: the accounts made so far were all made in test - the site has
 * never been live - so move them into the test slot. Left in place, the old
 * fields would be invisible (nothing reads them any more) and misleading.
 */
function gb_migrate_connect_accounts_to_test_mode() {
    global $wpdb;

    foreach ( gb_connect_mode_keys() as $key ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT pm.meta_id, pm.post_id FROM {$wpdb->postmeta} pm
               JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = %s AND p.post_type = 'gb_cleaner'",
            $key
        ) );

        foreach ( (array) $rows as $row ) {
            $has_test = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
                $row->post_id,
                gb_connect_mode_key( $key, 'test' )
            ) );

            if ( $has_test ) {
                $wpdb->delete( $wpdb->postmeta, array( 'meta_id' => $row->meta_id ) );
            } else {
                $wpdb->update( $wpdb->postmeta, array( 'meta_key' => gb_connect_mode_key( $key, 'test' ) ), array( 'meta_id' => $row->meta_id ) );
            }
            wp_cache_delete( (int) $row->post_id, 'post_meta' );
        }
    }
}
