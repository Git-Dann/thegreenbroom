<?php
/**
 * Membership subscriptions.
 *
 * inc/stripe.php already covers one-off payments for a booking and Connect for
 * paying cleaners. It has no notion of a recurring charge at all, which is the
 * whole membership model, so that lives here.
 *
 * Three plans, two audiences:
 *
 *   customer_quarterly  every 3 months   access to the cleaner directory
 *   customer_annual     every 12 months  the same, cheaper per month
 *   cleaner_monthly     every month      a cleaner's listing, 3 months free
 *
 * The amounts are NOT written here. They are read from the pricing panels the
 * public site already prints, so the page and the card charge cannot drift
 * apart: change the price in Settings and the next provision run moves Stripe
 * to match. If a panel's price cannot be read as a single clean number, this
 * refuses to provision rather than guessing at what to charge somebody.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * How long a cleaner gets before the first charge. The client's wording is
 * "free for 3 months, then GBP 14.95 per calendar month".
 */
const GB_CLEANER_TRIAL_DAYS = 90;

/**
 * The plans we sell, in the shape Stripe needs them.
 *
 * 'plan' is the key written to the membership record, so it must exist in
 * gb_membership_plans(). 'price_field' is the settings field the public panel
 * prints, which is where the amount comes from.
 */
function gb_subscription_catalogue() {
    return array(
        'customer_quarterly' => array(
            'label'       => 'Customer membership - quarterly',
            'audience'    => 'customer',
            'plan'        => 'quarterly',
            'price_field' => '_gb_price_cust_p1_price',
            'interval'    => 'month',
            'interval_count' => 3,
            'trial_days'  => 0,
        ),
        'customer_annual' => array(
            'label'       => 'Customer membership - annual',
            'audience'    => 'customer',
            'plan'        => 'annual',
            'price_field' => '_gb_price_cust_p2_price',
            'interval'    => 'year',
            'interval_count' => 1,
            'trial_days'  => 0,
        ),
        'cleaner_monthly' => array(
            'label'       => 'Cleaner listing - monthly',
            'audience'    => 'cleaner',
            'plan'        => 'monthly',
            'price_field' => '_gb_price_clean_price',
            'interval'    => 'month',
            'interval_count' => 1,
            'trial_days'  => GB_CLEANER_TRIAL_DAYS,
        ),
    );
}

function gb_subscription_settings_id() {
    return function_exists('gb_get_site_settings_id')
        ? (int) gb_get_site_settings_id()
        : (int) get_option('page_on_front');
}

/**
 * Turn a panel's price string into minor units.
 *
 * Deliberately strict. "GBP 24.95" and "24.95" both give 2495; anything with
 * no number, or more than one, returns a WP_Error rather than a guess. The
 * cost of guessing here is charging somebody the wrong amount.
 *
 * @return int|WP_Error
 */
function gb_parse_price_to_minor($text) {
    $text = trim((string) $text);

    if ($text === '') {
        return new WP_Error('gb_price_empty', 'The price is blank.');
    }

    if (!preg_match_all('/\d+(?:\.\d+)?/', $text, $matches)) {
        return new WP_Error('gb_price_unreadable', sprintf('No number found in "%s".', $text));
    }

    if (count($matches[0]) > 1) {
        return new WP_Error('gb_price_ambiguous', sprintf('More than one number in "%s", so it is not clear what to charge.', $text));
    }

    $amount = (float) $matches[0][0];

    if ($amount <= 0) {
        return new WP_Error('gb_price_zero', sprintf('"%s" is not an amount that can be charged.', $text));
    }

    // round(), not (int), so 24.95 * 100 does not land on 2494 through the
    // usual floating point business.
    return (int) round($amount * 100);
}

/**
 * The amount a plan should charge, read from the panel that advertises it.
 *
 * @return int|WP_Error minor units
 */
function gb_subscription_plan_amount($key) {
    $catalogue = gb_subscription_catalogue();

    if (!isset($catalogue[$key])) {
        return new WP_Error('gb_plan_unknown', 'Unknown plan.');
    }

    $raw = function_exists('gb_pricing_field')
        ? gb_pricing_field($catalogue[$key]['price_field'])
        : get_post_meta(gb_subscription_settings_id(), $catalogue[$key]['price_field'], true);

    $amount = gb_parse_price_to_minor($raw);

    if (is_wp_error($amount)) {
        return new WP_Error(
            $amount->get_error_code(),
            sprintf('%s (%s)', $amount->get_error_message(), $catalogue[$key]['label'])
        );
    }

    return $amount;
}

/* --------------------------------------------------------------------------
   WHAT HAS BEEN PROVISIONED
   -------------------------------------------------------------------------- */

/**
 * Stored per mode. A sandbox price id is meaningless against the live account,
 * so test and live keep separate records and switching mode cannot leave the
 * site pointing at a price that does not exist.
 */
function gb_subscription_record_key() {
    $mode = get_post_meta(gb_subscription_settings_id(), '_gb_stripe_mode', true) ?: 'test';
    $mode = in_array($mode, array('test', 'live'), true) ? $mode : 'test';

    return '_gb_stripe_subs_' . $mode;
}

function gb_get_subscription_records() {
    $stored = get_post_meta(gb_subscription_settings_id(), gb_subscription_record_key(), true);

    return is_array($stored) ? $stored : array();
}

function gb_get_subscription_record($key) {
    $records = gb_get_subscription_records();

    return isset($records[$key]) && is_array($records[$key]) ? $records[$key] : array();
}

function gb_save_subscription_record($key, array $record) {
    $records = gb_get_subscription_records();
    $records[$key] = $record;
    update_post_meta(gb_subscription_settings_id(), gb_subscription_record_key(), $records);
}

/**
 * The price a checkout should use, or '' when the plan has not been provisioned
 * in the current mode.
 */
function gb_get_subscription_price_id($key) {
    $record = gb_get_subscription_record($key);

    return isset($record['price_id']) ? (string) $record['price_id'] : '';
}

function gb_subscriptions_are_ready() {
    foreach (array_keys(gb_subscription_catalogue()) as $key) {
        if (gb_get_subscription_price_id($key) === '') {
            return false;
        }
    }

    return true;
}

/* --------------------------------------------------------------------------
   PROVISIONING
   --------------------------------------------------------------------------
   Creates the products and recurring prices in Stripe from the catalogue
   above. Safe to run as often as you like:

     - a product is reused when the stored id still resolves and is active;
     - a price is reused when the stored id still resolves AND its amount,
       currency and interval all still match the panel. A Stripe price is
       immutable, so a price change means a new price and the old one archived,
       which is exactly what this does.

   Nothing here touches an existing subscriber. Somebody already paying stays
   on the price they signed up at until they cancel, which is how it should be:
   a price change should not silently re-rate people who are mid-term.
   -------------------------------------------------------------------------- */

function gb_subscription_currency() {
    $currency = function_exists('gb_get_service_storefront_currency')
        ? strtolower((string) gb_get_service_storefront_currency())
        : 'gbp';

    return $currency !== '' ? $currency : 'gbp';
}

/**
 * Reuse the stored product when it still exists and is active.
 *
 * @return string|WP_Error product id
 */
function gb_provision_subscription_product($key, array $plan) {
    $record  = gb_get_subscription_record($key);
    $stored  = isset($record['product_id']) ? (string) $record['product_id'] : '';

    if ($stored !== '') {
        $existing = gb_stripe_api_request('products/' . rawurlencode($stored), array(), 'GET');

        if (!is_wp_error($existing) && !empty($existing['active'])) {
            return $stored;
        }
    }

    $created = gb_stripe_api_request('products', array(
        'name'                => $plan['label'],
        'metadata[gb_plan]'   => $key,
        'metadata[gb_source]' => 'thegreenbroom',
    ));

    if (is_wp_error($created)) {
        return $created;
    }

    return (string) $created['id'];
}

/**
 * @return array{price_id:string,reused:bool}|WP_Error
 */
function gb_provision_subscription_price($key, array $plan, $product_id, $amount, $currency) {
    $record = gb_get_subscription_record($key);
    $stored = isset($record['price_id']) ? (string) $record['price_id'] : '';

    if ($stored !== '') {
        $existing = gb_stripe_api_request('prices/' . rawurlencode($stored), array(), 'GET');

        if (!is_wp_error($existing)
            && !empty($existing['active'])
            && (int) $existing['unit_amount'] === (int) $amount
            && strtolower((string) $existing['currency']) === $currency
            && isset($existing['recurring']['interval'], $existing['recurring']['interval_count'])
            && $existing['recurring']['interval'] === $plan['interval']
            && (int) $existing['recurring']['interval_count'] === (int) $plan['interval_count']
        ) {
            return array('price_id' => $stored, 'reused' => true);
        }
    }

    $created = gb_stripe_api_request('prices', array(
        'product'                     => $product_id,
        'currency'                    => $currency,
        'unit_amount'                 => (int) $amount,
        'recurring[interval]'         => $plan['interval'],
        'recurring[interval_count]'   => (int) $plan['interval_count'],
        'metadata[gb_plan]'           => $key,
    ));

    if (is_wp_error($created)) {
        return $created;
    }

    // Retire the price this replaces, so the old amount cannot be checked out
    // by a stale link. Existing subscribers are unaffected: archiving a price
    // stops new use of it, it does not re-rate anybody already on it.
    if ($stored !== '' && $stored !== $created['id']) {
        gb_stripe_api_request('prices/' . rawurlencode($stored), array('active' => 'false'));
    }

    return array('price_id' => (string) $created['id'], 'reused' => false);
}

/**
 * Provision every plan.
 *
 * Returns a report rather than throwing, so the dashboard can show exactly
 * what happened per plan: what it charges, whether it was created or reused,
 * and the ids, which is what makes this checkable against the Stripe dashboard.
 */
function gb_stripe_provision_membership_products() {
    $config = gb_require_stripe_config(array('secret_key'));

    if (is_wp_error($config)) {
        return $config;
    }

    $currency = gb_subscription_currency();
    $report   = array();

    foreach (gb_subscription_catalogue() as $key => $plan) {
        $amount = gb_subscription_plan_amount($key);

        if (is_wp_error($amount)) {
            $report[$key] = array('ok' => false, 'label' => $plan['label'], 'error' => $amount->get_error_message());
            continue;
        }

        $product_id = gb_provision_subscription_product($key, $plan);

        if (is_wp_error($product_id)) {
            $report[$key] = array('ok' => false, 'label' => $plan['label'], 'error' => $product_id->get_error_message());
            continue;
        }

        $price = gb_provision_subscription_price($key, $plan, $product_id, $amount, $currency);

        if (is_wp_error($price)) {
            $report[$key] = array('ok' => false, 'label' => $plan['label'], 'error' => $price->get_error_message());
            continue;
        }

        gb_save_subscription_record($key, array(
            'product_id'     => $product_id,
            'price_id'       => $price['price_id'],
            'amount_minor'   => (int) $amount,
            'currency'       => $currency,
            'interval'       => $plan['interval'],
            'interval_count' => (int) $plan['interval_count'],
            'trial_days'     => (int) $plan['trial_days'],
            'provisioned'    => current_time('mysql'),
        ));

        $report[$key] = array(
            'ok'         => true,
            'label'      => $plan['label'],
            'amount'     => $amount,
            'currency'   => $currency,
            'product_id' => $product_id,
            'price_id'   => $price['price_id'],
            'reused'     => $price['reused'],
            'trial_days' => (int) $plan['trial_days'],
        );
    }

    return $report;
}

/* --------------------------------------------------------------------------
   THE DASHBOARD ACTION
   -------------------------------------------------------------------------- */

/**
 * Run the provisioning from Settings and report back what happened.
 *
 * The outcome is stored rather than squeezed into a redirect notice, because
 * the useful part is the per-plan detail - what each one charges and which
 * price id it landed on - and that is what makes it checkable against the
 * Stripe dashboard.
 */
function gb_handle_stripe_provision_subscriptions() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();

    if (!current_user_can('administrator') && !in_array('gb_manager', (array) $user->roles, true)) {
        wp_die('You do not have permission to set up Stripe products.', '', array('response' => 403));
    }

    if (
        empty($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gb_stripe_provision_subscriptions')
    ) {
        wp_die('Security check failed.', '', array('response' => 403));
    }

    $report = gb_stripe_provision_membership_products();

    if (is_wp_error($report)) {
        update_post_meta(gb_subscription_settings_id(), '_gb_stripe_subs_report', array(
            'when'  => current_time('mysql'),
            'error' => $report->get_error_message(),
            'plans' => array(),
        ));

        gb_redirect_manager_settings_with_stripe_notice('error', $report->get_error_message());
        exit;
    }

    $failed = 0;

    foreach ($report as $row) {
        if (empty($row['ok'])) {
            $failed++;
        }
    }

    update_post_meta(gb_subscription_settings_id(), '_gb_stripe_subs_report', array(
        'when'  => current_time('mysql'),
        'error' => '',
        'plans' => $report,
    ));

    $message = $failed === 0
        ? 'Membership plans are set up in Stripe.'
        : sprintf('%d of %d plans could not be set up. See the detail below.', $failed, count($report));

    gb_redirect_manager_settings_with_stripe_notice($failed === 0 ? 'success' : 'error', $message);
    exit;
}
add_action('admin_post_gb_stripe_provision_subscriptions', 'gb_handle_stripe_provision_subscriptions');

function gb_get_subscription_report() {
    $stored = get_post_meta(gb_subscription_settings_id(), '_gb_stripe_subs_report', true);

    return is_array($stored) ? $stored : array();
}

/**
 * Format minor units for display. Only ever used on our own stored amounts.
 */
function gb_format_minor_amount($minor, $currency = 'gbp') {
    $symbols = array('gbp' => '&pound;', 'usd' => '$', 'eur' => '&euro;');
    $symbol  = isset($symbols[strtolower($currency)]) ? $symbols[strtolower($currency)] : '';

    return $symbol . number_format(((int) $minor) / 100, 2);
}
