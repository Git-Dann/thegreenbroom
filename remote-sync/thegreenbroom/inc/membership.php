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
const GB_MEMBER_CANCELS  = 'gb_member_cancels_at'; // Y-m-d while a cancellation is pending, else empty

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
        return array( 'status' => 'none', 'plan' => '', 'started' => '', 'expires' => '', 'source' => '', 'cancels_at' => '' );
    }

    $status = (string) get_user_meta( $user_id, GB_MEMBER_STATUS, true );
    $expires = (string) get_user_meta( $user_id, GB_MEMBER_EXPIRES, true );

    // Read-time expiry, so a lapsed membership is never reported as current
    // just because the nightly sweep has not run yet.
    if ( $status === 'active' && $expires !== '' && strtotime( $expires ) && strtotime( $expires ) < current_time( 'timestamp' ) ) {
        $status = 'expired';
    }

    /*
     * A pending cancellation only means anything while the membership is still
     * running. Once it has actually stopped, the status says so and a leftover
     * date would be describing something that has already happened.
     */
    $cancels_at = $status === 'active' ? (string) get_user_meta( $user_id, GB_MEMBER_CANCELS, true ) : '';

    return array(
        'status'     => $status !== '' ? $status : 'none',
        'plan'       => (string) get_user_meta( $user_id, GB_MEMBER_PLAN, true ),
        'started'    => (string) get_user_meta( $user_id, GB_MEMBER_STARTED, true ),
        'expires'    => $expires,
        'source'     => (string) get_user_meta( $user_id, GB_MEMBER_SOURCE, true ),
        'cancels_at' => $cancels_at,
    );
}

/**
 * The one state a membership should be described by.
 *
 * Stored status and "is it cancelling" are two facts, and the Members screen
 * was printing both: a green ACTIVE pill with "Cancelled - not renewing" in
 * amber underneath it. Two answers to one question, on every row. Fine while
 * there are four people on the platform and unreadable at a hundred, which is
 * the whole point of the screen.
 *
 * Ending is its own state rather than a footnote on Active: somebody who has
 * cancelled and is running out their notice is neither staying nor gone, and
 * it is the state a manager most wants to pick out of a list.
 *
 * Every counter, pill and date label reads from here, so they cannot drift
 * apart or disagree with each other.
 *
 * @return array{key: string, label: string, colour: string, date_label: string}
 */
function gb_membership_display_state( array $membership ) {
    $status = isset( $membership['status'] ) ? (string) $membership['status'] : 'none';
    $ending = $status === 'active' && ! empty( $membership['cancels_at'] );

    if ( $ending ) {
        return array(
            'key'        => 'ending',
            'label'      => 'Ending',
            'colour'     => '#d97706',
            'date_label' => 'Ends',
        );
    }

    switch ( $status ) {
        case 'active':
            return array( 'key' => 'active', 'label' => 'Active', 'colour' => '#16a34a', 'date_label' => 'Renews' );
        case 'expired':
            return array( 'key' => 'expired', 'label' => 'Expired', 'colour' => '#ea580c', 'date_label' => 'Ended' );
        case 'cancelled':
            return array( 'key' => 'cancelled', 'label' => 'Cancelled', 'colour' => '#64748b', 'date_label' => 'Ended' );
    }

    return array( 'key' => 'none', 'label' => 'Not a member', 'colour' => '#94a3b8', 'date_label' => '' );
}

/**
 * The date that state is about, or '' when there is not one.
 *
 * A cancelling membership stops on its cancellation date, not on the renewal
 * date it would have had, and showing the renewal date next to "Ends" would be
 * the wrong day.
 */
function gb_membership_display_date( array $membership ) {
    $state = gb_membership_display_state( $membership );

    if ( $state['key'] === 'ending' ) {
        return (string) $membership['cancels_at'];
    }

    return isset( $membership['expires'] ) ? (string) $membership['expires'] : '';
}

/**
 * Record that a membership is set to stop, or that it is not.
 *
 * Stripe's portal offers "cancel at the end of the billing period", which is
 * the humane setting and the one in use: somebody who has paid to December
 * keeps what they paid for. But it leaves the subscription active, so without
 * this the account page would go on saying "renews on 28 December" to somebody
 * who had just cancelled - who would reasonably conclude it had not worked.
 *
 * Passing '' clears it, which is what a resumed subscription needs: Stripe
 * lets people turn renewal back on, and that has to undo this as cleanly as it
 * set it.
 */
function gb_set_membership_cancellation( $user_id, $cancels_at ) {
    $user_id    = (int) $user_id;
    $cancels_at = (string) $cancels_at;

    if ( $user_id < 1 ) {
        return;
    }

    if ( $cancels_at === '' ) {
        delete_user_meta( $user_id, GB_MEMBER_CANCELS );

        return;
    }

    update_user_meta( $user_id, GB_MEMBER_CANCELS, $cancels_at );
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
 * May this visitor book or message a cleaner?
 *
 * The gate as Nick set it out: anybody can browse the cleaners in their area
 * - the listings and every profile stay open, because few people will pay
 * before seeing who is available - and reaching out, by booking or by
 * message, is what membership buys.
 *
 * While the switch in Settings -> Customer access is off, everybody may.
 * Staff always may. Otherwise it takes an active membership.
 *
 * Until 30 September this switch did nothing at all: the check it controlled,
 * gb_can_access_directory(), was never called from anywhere, so switching it
 * on would have restricted nothing.
 */
function gb_can_contact_cleaners( $user_id = null ) {
    return gb_can_access_directory( $user_id );
}

/**
 * Where somebody who is not a member is sent to become one: the membership
 * panel on Find a Cleaner, next to the cleaners they were looking at.
 */
function gb_membership_join_url() {
    $page = get_page_by_path( 'cleaner-listings' );
    $base = $page ? get_permalink( $page ) : home_url( '/cleaner-listings/' );

    return $base . '#membership';
}

/**
 * Shown in place of Book Now and Message on a cleaner's profile.
 */
function gb_render_members_only_contact( $return_url = '' ) {
    $login = add_query_arg( 'redirect_to', rawurlencode( $return_url !== '' ? $return_url : home_url( '/' ) ), home_url( '/login/' ) );
    ?>
    <div class="gb-members-contact">
        <strong>Book or message this cleaner</strong>
        <p>Browsing is free. Booking and messaging cleaners is for members.</p>
        <a href="<?php echo esc_url( gb_membership_join_url() ); ?>" class="gb-btn-book">Become a member</a>
        <?php if ( ! is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( $login ); ?>" class="gb-members-contact-login">Already a member? Log in</a>
        <?php endif; ?>
    </div>
    <?php
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

    /*
     * A manager setting a membership by hand is overriding whatever Stripe
     * last said, so any pending cancellation it recorded no longer describes
     * this membership. Stripe's own path re-records it immediately afterwards
     * from the subscription, so only the manual case actually clears.
     */
    if ( $source === 'manual' ) {
        gb_set_membership_cancellation( $user_id, '' );
    }

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
