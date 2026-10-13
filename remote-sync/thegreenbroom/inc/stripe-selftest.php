<?php
/**
 * Stripe self-test, run from GitHub Actions (deploy.yml, "stripe_test").
 *
 * Proves the payment side end to end against the real Stripe account, without
 * anybody logging in, in three calls:
 *
 *   check   Read-only. Keys, the Stripe account itself, webhook endpoints and
 *           their events, the plans and their prices, the billing portal,
 *           cleaners' payout accounts and whether recent webhooks reached us.
 *   start   Test mode only. Makes a throwaway customer account and a real
 *           Stripe Checkout page for the cheapest customer plan. The workflow
 *           then fills that page in with Stripe's test card in a real browser.
 *   finish  Waits for Stripe's webhook to switch the membership on, opens a
 *           billing portal session, cancels the subscription, waits for the
 *           webhook to switch it off again, and deletes everything it made.
 *
 * Locked the same way as the email self-test (inc/email-selftest.php): check
 * and start each need a one-time token the workflow leaves on the server over
 * SSH, read and deleted before anything is compared. start hands back a second
 * one-time token for finish, which works once, for fifteen minutes.
 *
 * start refuses outright unless the site is in Test mode, so this can never
 * take a real payment or give a real membership away.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_STRIPE_SELFTEST_LOGIN = 'gb-stripe-selftest';
const GB_STRIPE_SELFTEST_EMAIL = 'stripe-selftest@thegreenbroom.uk';

add_action( 'init', function () {
    if ( empty( $_GET['gb_stripe_selftest'] ) ) {
        return;
    }

    nocache_headers();
    gb_stripe_selftest_handle();
}, 2 );

function gb_stripe_selftest_token_file() {
    return get_template_directory() . '/.gb-stripe-selftest-token.php';
}

function gb_stripe_selftest_respond( $code, array $body ) {
    status_header( $code );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo wp_json_encode( $body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    exit;
}

function gb_stripe_selftest_handle() {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        gb_stripe_selftest_respond( 405, array( 'error' => 'POST only.' ) );
    }

    $phase = isset( $_POST['phase'] ) ? sanitize_key( wp_unslash( $_POST['phase'] ) ) : 'check';
    $given = isset( $_POST['token'] ) ? (string) wp_unslash( $_POST['token'] ) : '';

    if ( $phase === 'finish' ) {
        gb_stripe_selftest_finish( $given );
    }

    $file = gb_stripe_selftest_token_file();

    if ( ! is_readable( $file ) ) {
        gb_stripe_selftest_respond( 403, array( 'error' => 'No test has been set up on the server.' ) );
    }

    // Single use once it matches. A wrong guess must not delete it: anybody
    // could otherwise break a test that is running by posting rubbish.
    $age  = time() - (int) filemtime( $file );
    // The file is rewritten for every token, but PHP's opcode cache can hand
    // back the copy it compiled last time; a second test inside the cache's
    // window then reads the previous, already-spent token.
    if ( function_exists( 'opcache_invalidate' ) ) {
        opcache_invalidate( $file, true );
    }
    $data = include $file;

    if ( ! is_array( $data ) || empty( $data['token'] ) || $age > 10 * MINUTE_IN_SECONDS || ! hash_equals( (string) $data['token'], $given ) ) {
        if ( $age > 10 * MINUTE_IN_SECONDS ) {
            @unlink( $file );
        }
        gb_stripe_selftest_respond( 403, array( 'error' => 'Token refused.' ) );
    }

    @unlink( $file );

    if ( $phase === 'start' ) {
        gb_stripe_selftest_start();
    }

    gb_stripe_selftest_respond( 200, gb_stripe_selftest_check() );
}

/* --------------------------------------------------------------------------
   CHECK
   -------------------------------------------------------------------------- */

/**
 * A Stripe request with a given key, so both modes can be looked at whatever
 * mode the site is in. Errors come back as array( 'error' => message ):
 * Stripe masks keys in its own messages, and nothing else is echoed.
 */
function gb_stripe_selftest_api( $secret, $endpoint, array $params = array(), $method = 'GET' ) {
    $url  = 'https://api.stripe.com/v1/' . ltrim( $endpoint, '/' );
    $args = array(
        'method'  => $method,
        'timeout' => 20,
        'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
    );

    if ( $params && $method === 'GET' ) {
        $url .= '?' . http_build_query( $params, '', '&' );
    } elseif ( $params ) {
        $args['body'] = http_build_query( $params, '', '&' );
    }

    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        return array( 'error' => 'Could not reach Stripe: ' . $response->get_error_message() );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( (int) wp_remote_retrieve_response_code( $response ) >= 400 || ! is_array( $data ) ) {
        return array( 'error' => is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'Stripe request failed.' );
    }

    return $data;
}

/**
 * Every Stripe setting for one mode, read straight from where Settings saves
 * them, since gb_get_stripe_config() only ever describes the current mode.
 */
function gb_stripe_selftest_keys( $mode ) {
    $id  = (int) get_option( 'page_on_front' );
    $get = static function ( $key ) use ( $id ) {
        return trim( (string) get_post_meta( $id, $key, true ) );
    };

    return array(
        'publishable'     => $get( "_gb_stripe_{$mode}_publishable_key" ),
        'secret'          => $get( "_gb_stripe_{$mode}_secret_key" ),
        'webhook'         => $get( "_gb_stripe_{$mode}_webhook_secret" ),
        'connect_webhook' => $get( "_gb_stripe_{$mode}_connect_webhook_secret" ),
        'v2_webhook'      => $get( "_gb_stripe_{$mode}_v2_webhook_secret" ),
    );
}

/**
 * 'ok', 'missing', or what is wrong with it. Never the value itself.
 */
function gb_stripe_selftest_key_state( $value, array $prefixes ) {
    if ( $value === '' || ( function_exists( 'gb_stripe_value_is_placeholder' ) && gb_stripe_value_is_placeholder( $value ) ) ) {
        return 'missing';
    }

    foreach ( $prefixes as $prefix ) {
        if ( strpos( $value, $prefix ) === 0 ) {
            return 'ok';
        }
    }

    return 'does not start with ' . implode( ' or ', $prefixes ) . ' (wrong key in this box?)';
}

function gb_stripe_selftest_check() {
    $config = gb_get_stripe_config();

    $report = array(
        'site'      => home_url( '/' ),
        'at'        => gmdate( 'c' ),
        'site_mode' => $config['mode'],
        'modes'     => array(),
        'problems'  => array(),
    );

    foreach ( array( 'test', 'live' ) as $mode ) {
        $keys = gb_stripe_selftest_keys( $mode );
        $sk   = $mode === 'live' ? array( 'sk_live_', 'rk_live_' ) : array( 'sk_test_', 'rk_test_' );

        $section = array(
            'keys' => array(
                'publishable_key'        => gb_stripe_selftest_key_state( $keys['publishable'], array( "pk_{$mode}_" ) ),
                'secret_key'             => gb_stripe_selftest_key_state( $keys['secret'], $sk ),
                'webhook_secret'         => gb_stripe_selftest_key_state( $keys['webhook'], array( 'whsec_' ) ),
                'connect_webhook_secret' => gb_stripe_selftest_key_state( $keys['connect_webhook'], array( 'whsec_' ) ),
                'v2_webhook_secret'      => gb_stripe_selftest_key_state( $keys['v2_webhook'], array( 'whsec_' ) ),
            ),
        );

        if ( $section['keys']['secret_key'] !== 'ok' ) {
            $section['note'] = 'No usable secret key, so nothing else in ' . $mode . ' mode can be checked.';
            $report['modes'][ $mode ] = $section;
            continue;
        }

        $secret = $keys['secret'];

        $section['account']        = gb_stripe_selftest_account( $secret );
        $section['webhooks']       = gb_stripe_selftest_webhooks( $secret );
        $section['plans']          = gb_stripe_selftest_plans( $secret, $mode );
        $section['billing_portal'] = gb_stripe_selftest_portal( $secret );
        $section['recent_events']  = gb_stripe_selftest_events( $secret );

        $report['modes'][ $mode ] = $section;
    }

    // How the site sees the caller's address, to confirm rate limits key on
    // the real visitor (gb_get_request_ip()) and not on the host's front server.
    $report['request_ip'] = array(
        'remote_addr'     => isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '',
        'x_forwarded_for' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
        'used'            => function_exists( 'gb_get_request_ip' ) ? gb_get_request_ip() : '',
    );

    $report['cleaner_payouts'] = gb_stripe_selftest_connect();
    $report['memberships']     = gb_stripe_selftest_membership_counts();
    $report['problems']        = gb_stripe_selftest_problems( $report );

    return $report;
}

function gb_stripe_selftest_account( $secret ) {
    $account = gb_stripe_selftest_api( $secret, 'account' );

    if ( isset( $account['error'] ) ) {
        return $account;
    }

    $due = isset( $account['requirements']['currently_due'] ) ? (array) $account['requirements']['currently_due'] : array();

    return array(
        'id'                 => isset( $account['id'] ) ? $account['id'] : '',
        'business_name'      => isset( $account['business_profile']['name'] ) ? (string) $account['business_profile']['name'] : '',
        'country'            => isset( $account['country'] ) ? $account['country'] : '',
        'default_currency'   => isset( $account['default_currency'] ) ? $account['default_currency'] : '',
        'charges_enabled'    => ! empty( $account['charges_enabled'] ),
        'payouts_enabled'    => ! empty( $account['payouts_enabled'] ),
        'details_submitted'  => ! empty( $account['details_submitted'] ),
        'requirements_due'   => $due,
        'disabled_reason'    => isset( $account['requirements']['disabled_reason'] ) ? (string) $account['requirements']['disabled_reason'] : '',
    );
}

/**
 * The webhook endpoints that point at this site, and which of the events
 * GO-LIVE.md step 6 asks for each one is missing.
 */
function gb_stripe_selftest_webhooks( $secret ) {
    $list = gb_stripe_selftest_api( $secret, 'webhook_endpoints', array( 'limit' => 100 ) );

    if ( isset( $list['error'] ) ) {
        return $list;
    }

    $ours     = gb_get_stripe_webhook_url();
    $wanted   = array(
        'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed',
        'checkout.session.expired', 'customer.subscription.created', 'customer.subscription.updated',
        'customer.subscription.deleted', 'customer.subscription.trial_will_end', 'invoice.paid',
        'charge.refunded',
        'invoice.payment_failed',
    );
    $found    = array();
    $have     = array();
    $connect  = false;

    foreach ( isset( $list['data'] ) ? (array) $list['data'] : array() as $endpoint ) {
        $url = isset( $endpoint['url'] ) ? (string) $endpoint['url'] : '';

        if ( untrailingslashit( $url ) !== untrailingslashit( $ours ) ) {
            continue;
        }

        $events = isset( $endpoint['enabled_events'] ) ? (array) $endpoint['enabled_events'] : array();

        $found[] = array(
            'id'     => isset( $endpoint['id'] ) ? $endpoint['id'] : '',
            'status' => isset( $endpoint['status'] ) ? $endpoint['status'] : '',
            'events' => $events,
        );

        if ( isset( $endpoint['status'] ) && $endpoint['status'] === 'enabled' ) {
            $have = array_merge( $have, $events );
            if ( in_array( 'account.updated', $events, true ) || in_array( '*', $events, true ) ) {
                $connect = true;
            }
        }
    }

    $missing = in_array( '*', $have, true ) ? array() : array_values( array_diff( $wanted, $have ) );

    return array(
        'url'                    => $ours,
        'endpoints_for_this_site' => $found,
        'missing_events'         => $missing,
        'account_updated_enabled' => $connect,
        // Stripe's API does not say whether an endpoint listens to connected
        // accounts, or list v2 destinations, so those two are taken on trust.
        'note'                   => 'Not visible through this API: whether the account.updated endpoint has "events on connected accounts" ticked, and the Accounts v2 destination (only its signing secret is checked above).',
    );
}

function gb_stripe_selftest_plans( $secret, $mode ) {
    $records = get_post_meta( gb_subscription_settings_id(), '_gb_stripe_subs_' . $mode, true );
    $records = is_array( $records ) ? $records : array();
    $out     = array();

    foreach ( gb_subscription_catalogue() as $key => $plan ) {
        $price_id = isset( $records[ $key ]['price_id'] ) ? (string) $records[ $key ]['price_id'] : '';
        $panel    = gb_subscription_plan_amount( $key );
        $row      = array(
            'panel_amount' => is_wp_error( $panel ) ? $panel->get_error_message() : gb_format_minor_amount( $panel ),
            'provisioned'  => $price_id !== '',
        );

        if ( $price_id !== '' ) {
            $price = gb_stripe_selftest_api( $secret, 'prices/' . rawurlencode( $price_id ) );

            if ( isset( $price['error'] ) ) {
                $row['stripe'] = $price;
            } else {
                $row['stripe'] = array(
                    'active'         => ! empty( $price['active'] ),
                    'amount'         => gb_format_minor_amount( (int) $price['unit_amount'], (string) $price['currency'] ),
                    'interval'       => isset( $price['recurring']['interval'] ) ? $price['recurring']['interval_count'] . ' ' . $price['recurring']['interval'] : 'not recurring',
                    'matches_panel'  => ! is_wp_error( $panel ) && (int) $price['unit_amount'] === (int) $panel,
                    'matches_period' => isset( $price['recurring']['interval'] )
                        && $price['recurring']['interval'] === $plan['interval']
                        && (int) $price['recurring']['interval_count'] === (int) $plan['interval_count'],
                );
            }
        }

        $out[ $key ] = $row;
    }

    return $out;
}

function gb_stripe_selftest_portal( $secret ) {
    $list = gb_stripe_selftest_api( $secret, 'billing_portal/configurations', array( 'is_default' => 'true', 'limit' => 1 ) );

    if ( isset( $list['error'] ) ) {
        return $list;
    }

    $config = isset( $list['data'][0] ) ? $list['data'][0] : null;

    if ( ! $config ) {
        return array( 'configured' => false );
    }

    $features = isset( $config['features'] ) ? $config['features'] : array();

    return array(
        'configured'          => true,
        'active'              => ! empty( $config['active'] ),
        'cancel_allowed'      => ! empty( $features['subscription_cancel']['enabled'] ),
        'cancel_mode'         => isset( $features['subscription_cancel']['mode'] ) ? $features['subscription_cancel']['mode'] : '',
        'update_card_allowed' => ! empty( $features['payment_method_update']['enabled'] ),
        'invoices_shown'      => ! empty( $features['invoice_history']['enabled'] ),
    );
}

/**
 * Webhooks Stripe is still trying to deliver. An event with pending_webhooks
 * above zero is one at least one endpoint has not yet accepted, which for an
 * event more than a few minutes old means our endpoint is refusing it.
 */
function gb_stripe_selftest_events( $secret ) {
    $list = gb_stripe_selftest_api( $secret, 'events', array(
        'limit'        => 100,
        'created[gte]' => time() - 7 * DAY_IN_SECONDS,
    ) );

    if ( isset( $list['error'] ) ) {
        return $list;
    }

    $total = 0;
    $stuck = array();

    foreach ( isset( $list['data'] ) ? (array) $list['data'] : array() as $event ) {
        $total++;

        if ( ! empty( $event['pending_webhooks'] ) && (int) $event['created'] < time() - 10 * MINUTE_IN_SECONDS ) {
            $stuck[] = $event['type'] . ' (' . gmdate( 'j M H:i', (int) $event['created'] ) . ')';
        }
    }

    return array(
        'last_7_days'        => $total . ( ! empty( $list['has_more'] ) ? '+' : '' ),
        'not_yet_delivered'  => $stuck,
    );
}

/**
 * Cleaners' payout accounts in the site's current mode, from what the site
 * has stored (kept current by the account.updated webhook).
 */
function gb_stripe_selftest_connect() {
    $cleaners = get_posts( array(
        'post_type'      => 'gb_cleaner',
        'post_status'    => array( 'publish', 'pending', 'draft' ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    $rows = array();

    foreach ( $cleaners as $id ) {
        $account = (string) get_post_meta( $id, '_gb_stripe_account_id', true );

        $rows[] = array(
            'cleaner'         => get_the_title( $id ),
            'status'          => get_post_status( $id ),
            'payout_account'  => $account !== '',
            'charges_enabled' => (bool) get_post_meta( $id, '_gb_stripe_charges_enabled', true ),
            'payouts_enabled' => (bool) get_post_meta( $id, '_gb_stripe_payouts_enabled', true ),
            'can_take_bookings' => function_exists( 'gb_cleaner_can_take_bookings' ) ? (bool) gb_cleaner_can_take_bookings( $id ) : null,
            'blocked_because' => function_exists( 'gb_cleaner_booking_block_reason' ) ? (string) gb_cleaner_booking_block_reason( $id ) : '',
        );
    }

    return array(
        'mode'     => function_exists( 'gb_connect_current_mode' ) ? gb_connect_current_mode() : '',
        'cleaners' => $rows,
    );
}

function gb_stripe_selftest_membership_counts() {
    $counts = array();

    $users = get_users( array(
        'meta_key' => '_gb_stripe_subscription_id',
        'fields'   => 'ID',
        'number'   => 500,
    ) );

    foreach ( $users as $user_id ) {
        $mode   = (string) get_user_meta( $user_id, '_gb_stripe_subscription_mode', true ) ?: 'unknown';
        $status = (string) get_user_meta( $user_id, '_gb_stripe_subscription_status', true ) ?: 'unknown';
        $key    = $mode . ': ' . $status;
        $counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
    }

    return $counts;
}

/**
 * The report's headline: what would actually stop somebody paying, in words.
 */
function gb_stripe_selftest_problems( array $report ) {
    $problems = array();

    foreach ( $report['modes'] as $mode => $section ) {
        $is_site_mode = $mode === $report['site_mode'];
        $label        = ucfirst( $mode ) . ' mode';

        foreach ( $section['keys'] as $name => $state ) {
            if ( $state !== 'ok' && ( $is_site_mode || $state !== 'missing' ) ) {
                $problems[] = "{$label}: {$name} {$state}.";
            }
        }

        if ( isset( $section['account']['error'] ) ) {
            $problems[] = "{$label}: Stripe refused the secret key: {$section['account']['error']}";
            continue;
        }

        if ( isset( $section['account'] ) && $mode === 'live' && empty( $section['account']['charges_enabled'] ) ) {
            $problems[] = 'Live mode: the Stripe account cannot take payments yet (activation not finished in Stripe).';
        }

        if ( ! empty( $section['webhooks']['missing_events'] ) ) {
            $problems[] = "{$label}: webhook events missing: " . implode( ', ', $section['webhooks']['missing_events'] ) . '.';
        }

        if ( isset( $section['webhooks']['account_updated_enabled'] ) && ! $section['webhooks']['account_updated_enabled'] && ! empty( $section['webhooks']['endpoints_for_this_site'] ) ) {
            $problems[] = "{$label}: no endpoint sends account.updated, so cleaners' payout status will not update on its own.";
        }

        if ( isset( $section['webhooks'] ) && ! isset( $section['webhooks']['error'] ) && empty( $section['webhooks']['endpoints_for_this_site'] ) ) {
            $problems[] = "{$label}: no webhook endpoint points at this site, so Stripe cannot tell it a payment happened.";
        }

        foreach ( isset( $section['plans'] ) ? $section['plans'] : array() as $key => $plan ) {
            if ( ! $plan['provisioned'] ) {
                if ( $is_site_mode ) {
                    $problems[] = "{$label}: plan {$key} not set up in Stripe (Settings, Payments, Set up the plans).";
                }
            } elseif ( isset( $plan['stripe']['error'] ) ) {
                $problems[] = "{$label}: plan {$key}: {$plan['stripe']['error']}";
            } elseif ( empty( $plan['stripe']['active'] ) || empty( $plan['stripe']['matches_panel'] ) || empty( $plan['stripe']['matches_period'] ) ) {
                $problems[] = "{$label}: plan {$key} in Stripe ({$plan['stripe']['amount']}, {$plan['stripe']['interval']}) does not match the panel ({$plan['panel_amount']}) or is archived.";
            }
        }

        if ( isset( $section['billing_portal'] ) && empty( $section['billing_portal']['configured'] ) && ! isset( $section['billing_portal']['error'] ) ) {
            $problems[] = "{$label}: the billing portal is not configured, so members cannot manage or cancel.";
        }

        if ( ! empty( $section['recent_events']['not_yet_delivered'] ) ) {
            $problems[] = "{$label}: Stripe has events it could not deliver to an endpoint: " . implode( ', ', array_slice( $section['recent_events']['not_yet_delivered'], 0, 5 ) ) . '.';
        }
    }

    $bookable = 0;
    foreach ( $report['cleaner_payouts']['cleaners'] as $row ) {
        $bookable += $row['can_take_bookings'] ? 1 : 0;
    }
    if ( $bookable === 0 ) {
        $problems[] = 'No cleaner can take a paid booking in ' . $report['cleaner_payouts']['mode'] . ' mode yet (none has finished payout setup).';
    }

    return $problems;
}

/* --------------------------------------------------------------------------
   START / FINISH: a real checkout in test mode
   -------------------------------------------------------------------------- */

function gb_stripe_selftest_plan_key() {
    // The cheapest customer plan: quarterly. No trial, so a card is charged
    // and a real invoice is paid.
    return 'customer_quarterly';
}

function gb_stripe_selftest_start() {
    $config = gb_get_stripe_config();

    if ( $config['mode'] !== 'test' ) {
        gb_stripe_selftest_respond( 409, array( 'error' => 'The site is in Live mode. The test purchase only ever runs in Test mode.' ) );
    }

    // Anything left from an earlier run that never finished goes first.
    gb_stripe_selftest_cleanup();

    $user_id = wp_insert_user( array(
        'user_login'   => GB_STRIPE_SELFTEST_LOGIN,
        'user_email'   => GB_STRIPE_SELFTEST_EMAIL,
        'user_pass'    => wp_generate_password( 32 ),
        'display_name' => 'Stripe self-test',
        'role'         => 'gb_customer',
    ) );

    if ( is_wp_error( $user_id ) ) {
        gb_stripe_selftest_respond( 500, array( 'error' => 'Could not create the test account: ' . $user_id->get_error_message() ) );
    }

    update_user_meta( $user_id, '_gb_stripe_selftest', 1 );

    $url = gb_membership_create_checkout_session( $user_id, gb_stripe_selftest_plan_key() );

    if ( is_wp_error( $url ) ) {
        gb_stripe_selftest_cleanup();
        gb_stripe_selftest_respond( 500, array( 'error' => 'Checkout could not be started: ' . $url->get_error_message() ) );
    }

    $finish = wp_generate_password( 40, false );

    update_option( 'gb_stripe_selftest_run', array(
        'hash'    => wp_hash_password( $finish ),
        'expires' => time() + 15 * MINUTE_IN_SECONDS,
        'user_id' => (int) $user_id,
        'started' => time(),
    ), false );

    gb_stripe_selftest_respond( 200, array(
        'checkout_url' => $url,
        'finish_token' => $finish,
        'plan'         => gb_stripe_selftest_plan_key(),
        'card'         => 'Use 4242 4242 4242 4242, any future date, any CVC.',
    ) );
}

/**
 * Wait up to $seconds for $test to come back true, checking every 3 seconds.
 * Membership state is read fresh each time, since the webhook writes it from
 * another request.
 */
function gb_stripe_selftest_wait( $seconds, callable $test ) {
    $start = time();

    do {
        wp_cache_flush();

        if ( $test() ) {
            return time() - $start;
        }

        sleep( 3 );
    } while ( time() - $start < $seconds );

    return -1;
}

function gb_stripe_selftest_finish( $given ) {
    $run = get_option( 'gb_stripe_selftest_run' );

    // A wrong token changes nothing, so it cannot be used to break a test in
    // progress; an expired run is tidied away.
    if ( ! is_array( $run ) || empty( $run['hash'] ) || time() > (int) $run['expires'] || ! wp_check_password( $given, $run['hash'] ) ) {
        if ( is_array( $run ) && time() > (int) $run['expires'] ) {
            delete_option( 'gb_stripe_selftest_run' );
            gb_stripe_selftest_cleanup();
        }
        gb_stripe_selftest_respond( 403, array( 'error' => 'Finish token refused.' ) );
    }

    delete_option( 'gb_stripe_selftest_run' );

    @set_time_limit( 150 );

    $user_id = (int) $run['user_id'];
    $steps   = array();
    $ok      = true;

    // 1. Stripe told us about the payment, and the membership switched on.
    // Both waits together stay inside the host's 60-second gateway limit.
    $waited = gb_stripe_selftest_wait( 25, static function () use ( $user_id ) {
        $m = gb_get_membership( $user_id );
        return isset( $m['status'] ) && $m['status'] === 'active';
    } );
    $membership = gb_get_membership( $user_id );
    $steps['membership_switched_on_by_webhook'] = $waited >= 0
        ? array( 'ok' => true, 'seconds' => $waited, 'plan' => $membership['plan'], 'renews' => $membership['expires'] )
        : array( 'ok' => false, 'detail' => 'No webhook reached the site within 25 seconds, or it did not activate the membership. Membership reads: ' . wp_json_encode( $membership ) );
    $ok = $ok && $waited >= 0;

    // 2. The subscription and its first invoice, from Stripe's side.
    $sub_id = (string) get_user_meta( $user_id, '_gb_stripe_subscription_id', true );
    if ( $sub_id !== '' ) {
        $sub = gb_stripe_api_request( 'subscriptions/' . rawurlencode( $sub_id ), array( 'expand' => array( 'latest_invoice' ) ), 'GET' );
        if ( is_wp_error( $sub ) ) {
            $steps['stripe_subscription'] = array( 'ok' => false, 'detail' => $sub->get_error_message() );
        } else {
            $invoice = isset( $sub['latest_invoice'] ) && is_array( $sub['latest_invoice'] ) ? $sub['latest_invoice'] : array();
            $steps['stripe_subscription'] = array(
                'ok'           => isset( $sub['status'] ) && $sub['status'] === 'active' && isset( $invoice['status'] ) && $invoice['status'] === 'paid',
                'status'       => isset( $sub['status'] ) ? $sub['status'] : '',
                'invoice'      => isset( $invoice['status'] ) ? $invoice['status'] : '',
                'amount_paid'  => isset( $invoice['amount_paid'] ) ? gb_format_minor_amount( (int) $invoice['amount_paid'], (string) $invoice['currency'] ) : '',
                'livemode'     => ! empty( $sub['livemode'] ),
            );
        }
    } else {
        $steps['stripe_subscription'] = array( 'ok' => false, 'detail' => 'The site never recorded a subscription id for the test account.' );
    }
    $ok = $ok && ! empty( $steps['stripe_subscription']['ok'] );

    // 3. The member can now do what members do.
    $steps['member_can_book_and_message'] = array(
        'ok' => function_exists( 'gb_membership_is_active_for' ) && gb_membership_is_active_for( $user_id, 'customer' ),
    );
    $ok = $ok && $steps['member_can_book_and_message']['ok'];

    // 4. "Manage or cancel" works.
    $portal = gb_membership_create_portal_session( $user_id );
    $steps['billing_portal_opens'] = is_wp_error( $portal )
        ? array( 'ok' => false, 'detail' => $portal->get_error_message() )
        : array( 'ok' => true );
    $ok = $ok && ! is_wp_error( $portal );

    // 5. Cancelling in Stripe switches it off on the site.
    if ( $sub_id !== '' ) {
        $cancelled = gb_stripe_api_request( 'subscriptions/' . rawurlencode( $sub_id ), array(), 'DELETE' );
        if ( is_wp_error( $cancelled ) ) {
            $steps['cancel_reaches_site'] = array( 'ok' => false, 'detail' => 'Stripe would not cancel: ' . $cancelled->get_error_message() );
        } else {
            $waited = gb_stripe_selftest_wait( 25, static function () use ( $user_id ) {
                $m = gb_get_membership( $user_id );
                return ! isset( $m['status'] ) || $m['status'] !== 'active';
            } );
            $steps['cancel_reaches_site'] = $waited >= 0
                ? array( 'ok' => true, 'seconds' => $waited )
                : array(
                    'ok'     => false,
                    'detail' => 'Cancelled in Stripe, but the site still shows the membership as active after 25 seconds.',
                    // What the site recorded, to tell "the webhook never came"
                    // (status still active) from "it came and was misread".
                    'site_saw' => array(
                        'stripe_status' => (string) get_user_meta( $user_id, '_gb_stripe_subscription_status', true ),
                        'membership'    => gb_get_membership( $user_id ),
                        'stripe_now'    => ( static function () use ( $sub_id ) {
                            $s = gb_stripe_api_request( 'subscriptions/' . rawurlencode( $sub_id ), array(), 'GET' );
                            return is_wp_error( $s ) ? $s->get_error_message() : ( $s['status'] ?? '' );
                        } )(),
                    ),
                );
        }
        $ok = $ok && ! empty( $steps['cancel_reaches_site']['ok'] );
    }

    $steps['cleanup'] = gb_stripe_selftest_cleanup();

    gb_stripe_selftest_respond( 200, array(
        'passed' => $ok,
        'mode'   => 'test',
        'steps'  => $steps,
    ) );
}

/**
 * Remove the throwaway account and its Stripe customer (test mode only; the
 * customer is only deleted when Stripe says it is a test customer).
 */
function gb_stripe_selftest_cleanup() {
    $user = get_user_by( 'login', GB_STRIPE_SELFTEST_LOGIN );

    if ( ! $user || ! get_user_meta( $user->ID, '_gb_stripe_selftest', true ) ) {
        return 'nothing to remove';
    }

    $customer = (string) get_user_meta( $user->ID, '_gb_stripe_customer_test', true );

    if ( $customer !== '' ) {
        $found = gb_stripe_api_request( 'customers/' . rawurlencode( $customer ), array(), 'GET' );

        if ( ! is_wp_error( $found ) && empty( $found['livemode'] ) && empty( $found['deleted'] ) ) {
            gb_stripe_api_request( 'customers/' . rawurlencode( $customer ), array(), 'DELETE' );
        }
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $user->ID );

    return 'test account and test customer removed';
}
