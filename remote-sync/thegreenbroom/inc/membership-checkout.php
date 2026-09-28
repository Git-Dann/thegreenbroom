<?php
/**
 * Taking the membership money.
 *
 * inc/stripe-subscriptions.php creates the products and prices in Stripe. This
 * is the other half: sending somebody to Stripe Checkout for one of them, and
 * turning what Stripe says afterwards into a membership on this site.
 *
 * Two rules shape the whole file:
 *
 * 1. Nothing here runs through wp-admin. The site bounces non-administrators
 *    away from /wp-admin/, which would silently swallow a customer pressing
 *    "Join" - they would be redirected before the handler ever ran. So the
 *    checkout starts from an `init` hook on the front end instead.
 *
 * 2. The browser never decides whether somebody has paid. Coming back from
 *    Stripe only shows a message; the membership itself is written from the
 *    webhook, which is signed, so a hand-typed success URL grants nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* --------------------------------------------------------------------------
   THE STRIPE CUSTOMER BEHIND A WORDPRESS USER
   -------------------------------------------------------------------------- */

/**
 * Per-mode, so a test customer id is never handed to the live account.
 */
function gb_membership_customer_meta_key() {
    $mode = get_post_meta( gb_subscription_settings_id(), '_gb_stripe_mode', true ) ?: 'test';
    $mode = in_array( $mode, array( 'test', 'live' ), true ) ? $mode : 'test';

    return '_gb_stripe_customer_' . $mode;
}

/**
 * Find or create the Stripe customer for a user.
 *
 * A stored id is verified before it is reused: an account can be wiped in the
 * Stripe dashboard while our copy of the id survives, and a checkout against a
 * deleted customer fails with an error nobody can act on.
 *
 * @return string|WP_Error customer id
 */
function gb_membership_get_stripe_customer( $user_id ) {
    $user_id = (int) $user_id;
    $user    = get_userdata( $user_id );

    if ( ! $user ) {
        return new WP_Error( 'gb_membership_no_user', 'That account could not be found.' );
    }

    $meta_key = gb_membership_customer_meta_key();
    $stored   = (string) get_user_meta( $user_id, $meta_key, true );

    if ( $stored !== '' ) {
        $existing = gb_stripe_api_request( 'customers/' . rawurlencode( $stored ), array(), 'GET' );

        if ( ! is_wp_error( $existing ) && empty( $existing['deleted'] ) ) {
            return $stored;
        }

        delete_user_meta( $user_id, $meta_key );
    }

    $created = gb_stripe_api_request( 'customers', array(
        'email'                => $user->user_email,
        'name'                 => $user->display_name !== '' ? $user->display_name : $user->user_login,
        'metadata[gb_user_id]' => $user_id,
        'metadata[gb_site]'    => home_url( '/' ),
    ) );

    if ( is_wp_error( $created ) ) {
        return $created;
    }

    if ( empty( $created['id'] ) ) {
        return new WP_Error( 'gb_membership_customer_failed', 'Stripe did not return a customer.' );
    }

    update_user_meta( $user_id, $meta_key, sanitize_text_field( $created['id'] ) );

    return (string) $created['id'];
}

/* --------------------------------------------------------------------------
   STARTING A CHECKOUT
   -------------------------------------------------------------------------- */

/**
 * Can this plan actually be bought right now?
 *
 * Needs a secret key and a provisioned price in the current mode. Without
 * both, the panels keep their old "create an account" button rather than
 * offering a purchase that would fail.
 */
function gb_membership_checkout_available( $plan_key ) {
    $catalogue = gb_subscription_catalogue();

    if ( ! isset( $catalogue[ $plan_key ] ) ) {
        return false;
    }

    $config = gb_get_stripe_config();

    if ( empty( $config['secret_key'] ) || gb_stripe_value_is_placeholder( $config['secret_key'] ) ) {
        return false;
    }

    return gb_get_subscription_price_id( $plan_key ) !== '';
}

/**
 * Is the person looking at this a manager or an administrator?
 */
function gb_membership_user_is_staff() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    if ( current_user_can( 'administrator' ) ) {
        return true;
    }

    return in_array( 'gb_manager', (array) wp_get_current_user()->roles, true );
}

/**
 * Should the buy button be on the page for this visitor?
 *
 * Available is not the same as visible. In Test Mode the prices are real
 * Stripe prices in a sandbox account, so a button would send a member of the
 * public to a checkout that takes a fake card and grants a real membership.
 * So in Test Mode only staff see it, which is enough to test the whole flow
 * end to end without the public ever meeting the sandbox.
 */
function gb_membership_checkout_visible( $plan_key ) {
    if ( ! gb_membership_checkout_available( $plan_key ) ) {
        return false;
    }

    $config = gb_get_stripe_config();

    if ( isset( $config['mode'] ) && $config['mode'] === 'live' ) {
        return true;
    }

    return gb_membership_user_is_staff();
}

/**
 * Where a checkout lands when it finishes, succeeds or is abandoned.
 */
function gb_membership_return_url( $plan_key, $result ) {
    $catalogue = gb_subscription_catalogue();
    $audience  = isset( $catalogue[ $plan_key ]['audience'] ) ? $catalogue[ $plan_key ]['audience'] : 'customer';
    // Success gets a page of its own, because "what now?" is a real question
    // and a one-line notice on the panel they just bought from does not answer
    // it. Everything else goes back where they were.
    if ( $result === 'success' ) {
        return add_query_arg( array( 'plan' => $plan_key ), home_url( '/membership-success/' ) );
    }

    $base = $audience === 'cleaner' ? home_url( '/join/' ) : home_url( '/cleaner-listings/' );

    return add_query_arg( array( 'gb_membership' => $result ), $base );
}

/**
 * Build the Checkout Session and hand back the URL to send the browser to.
 *
 * @return string|WP_Error
 */
function gb_membership_create_checkout_session( $user_id, $plan_key ) {
    $catalogue = gb_subscription_catalogue();

    if ( ! isset( $catalogue[ $plan_key ] ) ) {
        return new WP_Error( 'gb_membership_unknown_plan', 'That membership plan does not exist.' );
    }

    $plan     = $catalogue[ $plan_key ];

    /*
     * Never sell somebody a second copy of what they already have.
     *
     * Stripe will happily create a parallel subscription and bill both, and
     * the member would have no idea until two charges landed. Someone who
     * wants a different plan, or wants to stop, goes to the billing portal
     * instead - that is what it is for.
     */
    if ( gb_membership_is_active_for( $user_id, $plan['audience'] ) ) {
        return new WP_Error(
            'gb_membership_already_active',
            'You already have an active membership. You can change or cancel it from your account.'
        );
    }

    $price_id = gb_get_subscription_price_id( $plan_key );

    if ( $price_id === '' ) {
        return new WP_Error(
            'gb_membership_not_provisioned',
            'This plan has not been set up in Stripe yet. A manager can do it under Settings > Membership plans.'
        );
    }

    $customer_id = gb_membership_get_stripe_customer( $user_id );

    if ( is_wp_error( $customer_id ) ) {
        return $customer_id;
    }

    $params = array(
        'mode'                    => 'subscription',
        'customer'                => $customer_id,
        'client_reference_id'     => (string) $user_id,
        'line_items[0][price]'    => $price_id,
        'line_items[0][quantity]' => 1,
        'success_url'             => gb_membership_return_url( $plan_key, 'success' ),
        'cancel_url'              => gb_membership_return_url( $plan_key, 'cancelled' ),
        'metadata[gb_user_id]'    => $user_id,
        'metadata[gb_plan_key]'   => $plan_key,
        'subscription_data[metadata][gb_user_id]'  => $user_id,
        'subscription_data[metadata][gb_plan_key]' => $plan_key,
    );

    if ( (int) $plan['trial_days'] > 0 ) {
        $params['subscription_data[trial_period_days]'] = (int) $plan['trial_days'];
    }

    $response = gb_stripe_api_request( 'checkout/sessions', $params );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( empty( $response['url'] ) ) {
        return new WP_Error( 'gb_membership_no_session', 'Stripe did not return a checkout page.' );
    }

    return (string) $response['url'];
}

/**
 * The front-end handler. Deliberately not admin-post.php - see the file note.
 */
function gb_membership_handle_checkout_request() {
    if ( empty( $_POST['gb_membership_action'] ) ) {
        return;
    }

    if ( sanitize_key( wp_unslash( $_POST['gb_membership_action'] ) ) !== 'checkout' ) {
        return;
    }

    $plan_key = isset( $_POST['gb_plan'] ) ? sanitize_key( wp_unslash( $_POST['gb_plan'] ) ) : '';

    if ( empty( $_POST['gb_membership_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gb_membership_nonce'] ) ), 'gb_membership_checkout' ) ) {
        wp_safe_redirect( gb_membership_return_url( $plan_key, 'expired' ) );
        exit;
    }

    // Not logged in: keep the choice, send them to register, come back for it.
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( add_query_arg( array( 'plan' => $plan_key ), home_url( '/register/' ) ) );
        exit;
    }

    $url = gb_membership_create_checkout_session( get_current_user_id(), $plan_key );

    // Already a member: their account is where they change or stop it, and
    // sending them back to the price they cannot buy again explains nothing.
    if ( is_wp_error( $url ) && $url->get_error_code() === 'gb_membership_already_active' ) {
        wp_safe_redirect( add_query_arg( 'gb_billing', 'already', home_url( '/my-account' ) ) );
        exit;
    }

    if ( is_wp_error( $url ) ) {
        wp_safe_redirect( add_query_arg(
            array( 'gb_membership_msg' => rawurlencode( $url->get_error_message() ) ),
            gb_membership_return_url( $plan_key, 'error' )
        ) );
        exit;
    }

    // Stripe's own domain, so wp_safe_redirect() would refuse it.
    wp_redirect( $url );
    exit;
}
add_action( 'init', 'gb_membership_handle_checkout_request' );

/* --------------------------------------------------------------------------
   WHAT STRIPE TELLS US AFTERWARDS
   -------------------------------------------------------------------------- */

/**
 * Which WordPress user a subscription belongs to.
 *
 * The metadata we set at checkout is the answer; the customer id is the
 * fallback for anything created in the Stripe dashboard by hand.
 */
function gb_membership_user_from_subscription( array $subscription ) {
    if ( ! empty( $subscription['metadata']['gb_user_id'] ) ) {
        $user_id = (int) $subscription['metadata']['gb_user_id'];

        if ( $user_id > 0 && get_userdata( $user_id ) ) {
            return $user_id;
        }
    }

    $customer_id = '';

    if ( ! empty( $subscription['customer'] ) ) {
        $customer_id = is_array( $subscription['customer'] )
            ? ( isset( $subscription['customer']['id'] ) ? (string) $subscription['customer']['id'] : '' )
            : (string) $subscription['customer'];
    }

    if ( $customer_id === '' ) {
        return 0;
    }

    $users = get_users( array(
        'meta_key'   => gb_membership_customer_meta_key(),
        'meta_value' => $customer_id,
        'fields'     => 'ID',
        'number'     => 1,
    ) );

    return empty( $users ) ? 0 : (int) $users[0];
}

/**
 * Which of our plans a subscription is for.
 *
 * Metadata first, then the price id, so a subscription started outside our
 * checkout still lands on the right plan.
 */
function gb_membership_plan_key_from_subscription( array $subscription ) {
    $catalogue = gb_subscription_catalogue();

    if ( ! empty( $subscription['metadata']['gb_plan_key'] ) ) {
        $key = sanitize_key( $subscription['metadata']['gb_plan_key'] );

        if ( isset( $catalogue[ $key ] ) ) {
            return $key;
        }
    }

    $price_id = '';

    if ( ! empty( $subscription['items']['data'][0]['price']['id'] ) ) {
        $price_id = (string) $subscription['items']['data'][0]['price']['id'];
    }

    if ( $price_id === '' ) {
        return '';
    }

    foreach ( array_keys( $catalogue ) as $key ) {
        if ( gb_get_subscription_price_id( $key ) === $price_id ) {
            return $key;
        }
    }

    return '';
}

/**
 * Stripe's subscription status, in the two words this site cares about.
 *
 * 'past_due' counts as still in: the card retried and failed, the paid-for
 * period has not ended, and locking somebody out mid-term over a retry is the
 * wrong answer. Stripe cancels the subscription when it gives up, and that
 * arrives here as 'canceled'.
 */
function gb_membership_status_from_stripe( $stripe_status ) {
    switch ( (string) $stripe_status ) {
        case 'active':
        case 'trialing':
        case 'past_due':
            return 'active';
        case 'canceled':
        case 'incomplete_expired':
        case 'unpaid':
            return 'cancelled';
        default:
            // 'incomplete' and anything Stripe adds later: nothing paid, so
            // nothing granted, but nothing taken away either.
            return '';
    }
}

/**
 * When the paid-for period ends.
 *
 * Stripe moved current_period_end off the subscription and onto each
 * subscription item in the 2025-03-31 API release; this account is on a later
 * version again, so the old top-level field is simply absent and reading only
 * that gave no renewal date at all. Both places are checked, newest first,
 * with trial_end as the answer while a trial is running.
 */
function gb_membership_period_end( array $subscription ) {
    if ( ! empty( $subscription['items']['data'] ) && is_array( $subscription['items']['data'] ) ) {
        $latest = 0;

        foreach ( $subscription['items']['data'] as $item ) {
            if ( ! empty( $item['current_period_end'] ) ) {
                $latest = max( $latest, (int) $item['current_period_end'] );
            }
        }

        if ( $latest > 0 ) {
            return $latest;
        }
    }

    if ( ! empty( $subscription['current_period_end'] ) ) {
        return (int) $subscription['current_period_end'];
    }

    // A trial that has not produced an invoice yet.
    if ( ! empty( $subscription['trial_end'] ) ) {
        return (int) $subscription['trial_end'];
    }

    return 0;
}

/**
 * The single place a Stripe subscription becomes site state.
 */
function gb_membership_apply_subscription( array $subscription ) {
    if ( empty( $subscription['id'] ) ) {
        return;
    }

    $user_id = gb_membership_user_from_subscription( $subscription );

    if ( $user_id < 1 ) {
        return;
    }

    $plan_key = gb_membership_plan_key_from_subscription( $subscription );

    if ( $plan_key === '' ) {
        return;
    }

    $catalogue = gb_subscription_catalogue();
    $plan      = $catalogue[ $plan_key ];
    $status    = gb_membership_status_from_stripe( isset( $subscription['status'] ) ? $subscription['status'] : '' );

    if ( $status === '' ) {
        return;
    }

    $period_end = gb_membership_period_end( $subscription );
    $expires    = $period_end > 0 ? gmdate( 'Y-m-d', $period_end ) : '';

    $subscription_id = sanitize_text_field( (string) $subscription['id'] );

    // Whatever they picked before they had an account, they have now bought.
    if ( $status === 'active' ) {
        gb_membership_clear_pending_plan( $user_id );
    }

    update_user_meta( $user_id, '_gb_stripe_subscription_id', $subscription_id );
    update_user_meta( $user_id, '_gb_stripe_subscription_status', sanitize_text_field( (string) $subscription['status'] ) );

    if ( $plan['audience'] === 'cleaner' ) {
        gb_membership_apply_cleaner_listing( $user_id, $status, $expires, $subscription );

        return;
    }

    $note = sprintf(
        'Stripe subscription %s (%s)',
        $subscription_id,
        sanitize_text_field( (string) $subscription['status'] )
    );

    gb_set_membership( $user_id, $status, $plan['plan'], $expires, 'stripe', $note );
}

/**
 * A cleaner's listing is not a customer membership - gb_membership_plans()
 * only knows quarterly and annual - so it gets its own record, mirrored onto
 * the cleaner profile so a manager can see it without opening Stripe.
 */
function gb_membership_apply_cleaner_listing( $user_id, $status, $expires, array $subscription ) {
    $stripe_status = sanitize_text_field( (string) ( isset( $subscription['status'] ) ? $subscription['status'] : '' ) );

    update_user_meta( $user_id, '_gb_listing_status', $status );
    update_user_meta( $user_id, '_gb_listing_renews', $expires );
    update_user_meta( $user_id, '_gb_listing_stripe_status', $stripe_status );

    $cleaners = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'author'         => (int) $user_id,
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => 5,
        'fields'         => 'ids',
    ) );

    foreach ( $cleaners as $cleaner_id ) {
        update_post_meta( $cleaner_id, '_gb_listing_status', $status );
        update_post_meta( $cleaner_id, '_gb_listing_renews', $expires );
        update_post_meta( $cleaner_id, '_gb_listing_stripe_status', $stripe_status );
    }
}

/**
 * Fetch a subscription by id and apply it. Used where an event carries the id
 * rather than the object.
 */
function gb_membership_sync_subscription( $subscription_id ) {
    $subscription_id = sanitize_text_field( (string) $subscription_id );

    if ( $subscription_id === '' ) {
        return;
    }

    $subscription = gb_stripe_api_request( 'subscriptions/' . rawurlencode( $subscription_id ), array(), 'GET' );

    if ( is_wp_error( $subscription ) || ! is_array( $subscription ) ) {
        return;
    }

    gb_membership_apply_subscription( $subscription );
}

/**
 * Called from the verified webhook in inc/stripe.php.
 *
 * Every branch ends in gb_membership_apply_subscription(), so there is one
 * description of what a subscription means no matter which event arrived.
 */
function gb_membership_handle_stripe_event( $event_type, $event_object ) {
    if ( ! is_array( $event_object ) ) {
        return;
    }

    switch ( (string) $event_type ) {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            if ( ! empty( $event_object['mode'] ) && $event_object['mode'] === 'subscription' && ! empty( $event_object['subscription'] ) ) {
                gb_membership_sync_subscription( $event_object['subscription'] );
            }
            break;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
        case 'customer.subscription.trial_will_end':
            gb_membership_apply_subscription( $event_object );
            break;

        case 'invoice.paid':
        case 'invoice.payment_failed':
            if ( ! empty( $event_object['subscription'] ) ) {
                gb_membership_sync_subscription( $event_object['subscription'] );
            }
            break;
    }
}

/* --------------------------------------------------------------------------
   COMING BACK FROM STRIPE
   -------------------------------------------------------------------------- */

/**
 * The message shown on return. It reports what Stripe said, not what the site
 * has recorded - the webhook may land a second after the browser does, and
 * "we are setting it up" is honest either way.
 */
function gb_membership_return_notice() {
    if ( empty( $_GET['gb_membership'] ) ) {
        return '';
    }

    $result = sanitize_key( wp_unslash( $_GET['gb_membership'] ) );
    $detail = isset( $_GET['gb_membership_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['gb_membership_msg'] ) ) ) : '';

    $messages = array(
        'success'   => array( 'ok', 'Thank you - your membership is being set up. It can take a few seconds to show here.' ),
        'cancelled' => array( 'info', 'No payment was taken. You can pick a plan whenever you are ready.' ),
        'expired'   => array( 'error', 'That page had been open a while, so we stopped. Please try again.' ),
        'error'     => array( 'error', $detail !== '' ? $detail : 'We could not start the checkout. Please try again.' ),
    );

    if ( ! isset( $messages[ $result ] ) ) {
        return '';
    }

    list( $tone, $text ) = $messages[ $result ];

    return '<div class="gb-membership-notice gb-membership-notice-' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</div>';
}

/* --------------------------------------------------------------------------
   WHAT SOMEBODY ALREADY HAS
   -------------------------------------------------------------------------- */

/**
 * Is this user already paying for this side of the platform?
 *
 * Customers and cleaners are recorded separately - a cleaner's listing is not
 * a customer membership - so the answer depends on which one is being asked
 * about, and a cleaner buying customer access is a legitimate thing to do.
 */
function gb_membership_is_active_for( $user_id, $audience ) {
    $user_id = (int) $user_id;

    if ( $user_id < 1 ) {
        return false;
    }

    if ( $audience === 'cleaner' ) {
        return (string) get_user_meta( $user_id, '_gb_listing_status', true ) === 'active';
    }

    if ( ! function_exists( 'gb_get_membership' ) ) {
        return false;
    }

    $membership = gb_get_membership( $user_id );

    return $membership['status'] === 'active';
}

/**
 * The plan somebody picked before they had an account.
 *
 * Clicking a price while logged out sent them to registration and lost the
 * choice entirely: they signed up and were shown nothing about the thing they
 * had just tried to buy. It is remembered here and offered back on their
 * account page once they are in.
 *
 * Deliberately not resumed by redirecting them straight to Stripe after they
 * confirm their email - being thrown at a payment page by an email link is
 * alarming, and there is no way back from it.
 */
function gb_membership_set_pending_plan( $user_id, $plan_key ) {
    $catalogue = gb_subscription_catalogue();
    $plan_key  = sanitize_key( $plan_key );

    if ( ! isset( $catalogue[ $plan_key ] ) ) {
        return false;
    }

    return (bool) update_user_meta( (int) $user_id, '_gb_pending_plan', $plan_key );
}

function gb_membership_get_pending_plan( $user_id ) {
    $stored    = (string) get_user_meta( (int) $user_id, '_gb_pending_plan', true );
    $catalogue = gb_subscription_catalogue();

    return isset( $catalogue[ $stored ] ) ? $stored : '';
}

function gb_membership_clear_pending_plan( $user_id ) {
    delete_user_meta( (int) $user_id, '_gb_pending_plan' );
}

/* --------------------------------------------------------------------------
   MANAGING A MEMBERSHIP AFTERWARDS
   -------------------------------------------------------------------------- */

/**
 * A link into Stripe's billing portal.
 *
 * This is where somebody updates a card, changes plan, downloads an invoice or
 * cancels. Building those four screens here would be re-implementing something
 * Stripe already does properly, and getting cancellation wrong is the kind of
 * mistake that ends up in a complaint rather than a bug report.
 *
 * @return string|WP_Error
 */
function gb_membership_create_portal_session( $user_id, $return_url = '' ) {
    $customer_id = (string) get_user_meta( (int) $user_id, gb_membership_customer_meta_key(), true );

    if ( $customer_id === '' ) {
        return new WP_Error( 'gb_membership_no_customer', 'We have no billing record for this account yet.' );
    }

    if ( $return_url === '' ) {
        $return_url = home_url( '/my-account' );
    }

    $response = gb_stripe_api_request( 'billing_portal/sessions', array(
        'customer'   => $customer_id,
        'return_url' => $return_url,
    ) );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( empty( $response['url'] ) ) {
        return new WP_Error( 'gb_membership_no_portal', 'Stripe did not return a billing page.' );
    }

    return (string) $response['url'];
}

/**
 * Front-end handler for "manage my membership". Not admin-post.php, for the
 * same reason nothing else here is.
 */
function gb_membership_handle_portal_request() {
    if ( empty( $_POST['gb_membership_action'] ) ) {
        return;
    }

    if ( sanitize_key( wp_unslash( $_POST['gb_membership_action'] ) ) !== 'portal' ) {
        return;
    }

    if (
        empty( $_POST['gb_membership_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gb_membership_nonce'] ) ), 'gb_membership_portal' )
    ) {
        wp_safe_redirect( add_query_arg( 'gb_billing', 'expired', home_url( '/my-account' ) ) );
        exit;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/login' ) );
        exit;
    }

    $url = gb_membership_create_portal_session( get_current_user_id(), home_url( '/my-account' ) );

    if ( is_wp_error( $url ) ) {
        wp_safe_redirect( add_query_arg(
            array( 'gb_billing' => 'error', 'gb_billing_msg' => rawurlencode( $url->get_error_message() ) ),
            home_url( '/my-account' )
        ) );
        exit;
    }

    wp_redirect( $url );
    exit;
}
add_action( 'init', 'gb_membership_handle_portal_request' );
