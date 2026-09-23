<?php
/**
 * Customer (subscriber) memberships.
 *
 * The Subscribers' Terms describe a paid membership for access to the cleaner
 * database. Stripe credentials are not available yet, so nothing here charges
 * anybody and nothing is gated: the database stays open exactly as it is.
 *
 * What this does provide is everything around the payment - a membership state
 * on a customer account, manager controls to grant and cancel by hand, expiry,
 * and a single gate function that currently always answers "let them in". When
 * Stripe arrives, billing writes to the same state and the gate is turned on
 * from Settings. That is a switch, not a build.
 *
 * Cleaners' PROFESSIONAL membership is a separate thing and already exists as
 * post meta on the cleaner record; this is the customer side.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_MEMBER_STATUS   = 'gb_member_status';   // none | active | expired | cancelled
const GB_MEMBER_PLAN     = 'gb_member_plan';     // quarterly | annual
const GB_MEMBER_STARTED  = 'gb_member_started';
const GB_MEMBER_EXPIRES  = 'gb_member_expires';
const GB_MEMBER_SOURCE   = 'gb_member_source';   // manual | stripe
const GB_MEMBER_HISTORY  = 'gb_member_history';

function gb_membership_plans() {
    return array(
        'quarterly' => array( 'label' => 'Quarterly', 'months' => 3 ),
        'annual'    => array( 'label' => 'Annual',    'months' => 12 ),
    );
}

/**
 * Is the database gated? Currently always false, and deliberately so: the
 * setting exists so the day it changes is a decision rather than a deployment.
 */
function gb_membership_gate_enabled() {
    $settings_id = function_exists( 'gb_get_site_settings_id' ) ? gb_get_site_settings_id() : (int) get_option( 'page_on_front' );

    if ( ! $settings_id ) {
        return false;
    }

    /*
     * Compare as a string rather than strictly against '1'. The failure mode of
     * a strict check that misses is a manager believing the database is locked
     * while it is wide open, which is the worst way round to be wrong.
     */
    return (string) get_post_meta( $settings_id, '_gb_membership_gate', true ) === '1';
}

function gb_get_membership( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 ) {
        return array( 'status' => 'none', 'plan' => '', 'started' => '', 'expires' => '', 'source' => '' );
    }

    $status = (string) get_user_meta( $user_id, GB_MEMBER_STATUS, true );
    $expires = (string) get_user_meta( $user_id, GB_MEMBER_EXPIRES, true );

    // Read-time expiry, so a lapsed membership is never reported as current
    // just because the nightly sweep has not run yet.
    if ( $status === 'active' && $expires !== '' && strtotime( $expires ) && strtotime( $expires ) < current_time( 'timestamp' ) ) {
        $status = 'expired';
    }

    return array(
        'status'  => $status !== '' ? $status : 'none',
        'plan'    => (string) get_user_meta( $user_id, GB_MEMBER_PLAN, true ),
        'started' => (string) get_user_meta( $user_id, GB_MEMBER_STARTED, true ),
        'expires' => $expires,
        'source'  => (string) get_user_meta( $user_id, GB_MEMBER_SOURCE, true ),
    );
}

function gb_user_is_member( $user_id ) {
    $membership = gb_get_membership( $user_id );

    return $membership['status'] === 'active';
}

/**
 * The single question the rest of the site should ask.
 *
 * Every future paywall calls this and nothing else. While the gate is off it
 * returns true for everyone, so wiring it in now changes no behaviour - which
 * is the point: the call sites can be added and tested long before the gate is
 * ever switched on.
 */
/**
 * Administrators and platform managers. Mirrors the check the verification
 * document viewer uses, so the two cannot disagree about who is staff.
 */
function gb_user_is_staff( $user_id = null ) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ( $user_id < 1 ) {
        return false;
    }

    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }

    $user = get_userdata( $user_id );

    return $user && in_array( 'gb_manager', (array) $user->roles, true );
}

function gb_can_access_directory( $user_id = null ) {
    if ( ! gb_membership_gate_enabled() ) {
        return true;
    }

    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;

    /*
     * Managers must never be gated out of their own directory. This checked a
     * gb_user_is_manager() that does not exist, so function_exists() swallowed
     * it and the test fell through to manage_options - which the gb_manager
     * role does NOT have. Every manager would have lost access the moment the
     * gate was switched on, and only then.
     */
    if ( gb_user_is_staff( $user_id ) ) {
        return true;
    }

    return gb_user_is_member( $user_id );
}

/**
 * Record a membership change. Manual today, Stripe later - same entry point.
 */
function gb_set_membership( $user_id, $status, $plan = '', $expires = '', $source = 'manual', $note = '' ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
        return false;
    }

    $allowed = array( 'none', 'active', 'expired', 'cancelled' );
    $status  = in_array( $status, $allowed, true ) ? $status : 'none';
    $plans   = gb_membership_plans();
    $plan    = isset( $plans[ $plan ] ) ? $plan : '';

    $before = gb_get_membership( $user_id );

    if ( $status === 'active' ) {
        if ( $expires === '' && $plan !== '' ) {
            $expires = gmdate( 'Y-m-d', strtotime( '+' . $plans[ $plan ]['months'] . ' months', current_time( 'timestamp' ) ) );
        }
        if ( $before['started'] === '' ) {
            update_user_meta( $user_id, GB_MEMBER_STARTED, current_time( 'Y-m-d' ) );
        }
    }

    update_user_meta( $user_id, GB_MEMBER_STATUS, $status );
    update_user_meta( $user_id, GB_MEMBER_PLAN, $plan );
    update_user_meta( $user_id, GB_MEMBER_EXPIRES, $expires );
    update_user_meta( $user_id, GB_MEMBER_SOURCE, $source );

    $actor = get_current_user_id();
    $who   = $actor ? ( get_userdata( $actor ) ? get_userdata( $actor )->user_email : 'User #' . $actor ) : 'System';

    $history = get_user_meta( $user_id, GB_MEMBER_HISTORY, true );
    $history = is_array( $history ) ? $history : array();
    $history[] = array(
        'at'      => current_time( 'mysql' ),
        'from'    => $before['status'],
        'to'      => $status,
        'plan'    => $plan,
        'expires' => $expires,
        'source'  => $source,
        'by'      => $who,
        'note'    => sanitize_text_field( $note ),
    );
    if ( count( $history ) > 200 ) {
        $history = array_slice( $history, -200 );
    }
    update_user_meta( $user_id, GB_MEMBER_HISTORY, $history );

    return true;
}

function gb_get_membership_history( $user_id ) {
    $history = get_user_meta( (int) $user_id, GB_MEMBER_HISTORY, true );

    return is_array( $history ) ? $history : array();
}

/**
 * Nightly: mark lapsed memberships expired so reporting is honest even though
 * gb_get_membership() already treats them as expired on read.
 */
function gb_expire_memberships() {
    $users = get_users( array(
        'meta_key'   => GB_MEMBER_STATUS,
        'meta_value' => 'active',
        'fields'     => 'ID',
        'number'     => 500,
    ) );

    foreach ( $users as $user_id ) {
        $expires = (string) get_user_meta( $user_id, GB_MEMBER_EXPIRES, true );
        if ( $expires === '' || ! strtotime( $expires ) ) {
            continue;
        }
        if ( strtotime( $expires ) < current_time( 'timestamp' ) ) {
            gb_set_membership( $user_id, 'expired', (string) get_user_meta( $user_id, GB_MEMBER_PLAN, true ), $expires, (string) get_user_meta( $user_id, GB_MEMBER_SOURCE, true ), 'Membership period ended' );
        }
    }
}
add_action( 'gb_expire_memberships', 'gb_expire_memberships' );

function gb_schedule_membership_expiry() {
    if ( ! wp_next_scheduled( 'gb_expire_memberships' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gb_expire_memberships' );
    }
}
add_action( 'init', 'gb_schedule_membership_expiry', 31 );
