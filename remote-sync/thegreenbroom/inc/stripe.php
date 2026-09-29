<?php
/**
 * Stripe settings and Connect
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   6. STRIPE API SETTINGS ENGINE
   ========================================================================== */

function gb_get_stripe_webhook_url() {
    return admin_url('admin-post.php?action=gb_stripe_webhook');
}

function gb_get_allowed_payout_intervals() {
    return ['manual', 'daily', 'weekly', 'monthly'];
}

function gb_normalize_payout_interval($interval) {
    $interval = sanitize_key((string) $interval);
    return in_array($interval, gb_get_allowed_payout_intervals(), true) ? $interval : 'daily';
}

function gb_find_cleaner_by_account_id($account_id) {
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return 0;
    }

    $query = new WP_Query([
        'post_type'      => 'gb_cleaner',
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            ['key' => '_gb_stripe_account_id', 'value' => $account_id],
        ],
        'no_found_rows'  => true,
    ]);

    return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
}

function gb_cleaner_connect_status_label($charges_enabled, $payouts_enabled, $details_submitted, $requirements_due_count) {
    if (!$details_submitted) {
        return 'needs_onboarding';
    }
    if ($requirements_due_count > 0) {
        return 'restricted';
    }
    if ($charges_enabled && $payouts_enabled) {
        return 'active';
    }
    return 'pending_review';
}

function gb_get_connect_country_code() {
    return 'GB';
}

function gb_stripe_unwrap_data_object($payload) {
    if (is_array($payload) && isset($payload['data']) && is_array($payload['data']) && !isset($payload['id'])) {
        return $payload['data'];
    }
    return is_array($payload) ? $payload : [];
}

function gb_get_connect_account_from_stripe($account_id) {
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Missing connected account ID.');
    }

    // Prefer Connect v2 retrieval for onboarding requirement status.
    $account_v2 = gb_stripe_api_request_v2(
        'core/accounts/' . rawurlencode($account_id),
        ['include' => ['configuration.recipient', 'requirements']],
        'GET'
    );
    if (!is_wp_error($account_v2)) {
        $unwrapped = gb_stripe_unwrap_data_object($account_v2);
        if (!empty($unwrapped['id'])) {
            return $unwrapped;
        }
    }

    // Backward-compatible fallback for existing v1 Express accounts.
    return gb_stripe_api_request('accounts/' . rawurlencode($account_id), [], 'GET');
}

function gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account) {
    $cleaner_id = absint($cleaner_id);
    $account = gb_stripe_unwrap_data_object($account);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner' || !is_array($account) || empty($account['id'])) {
        return;
    }

    $is_v2_account = isset($account['configuration']['recipient']) || isset($account['defaults']['responsibilities']);

    if ($is_v2_account) {
        $transfer_status = isset($account['configuration']['recipient']['capabilities']['stripe_balance']['stripe_transfers']['status'])
            ? sanitize_key((string) $account['configuration']['recipient']['capabilities']['stripe_balance']['stripe_transfers']['status'])
            : '';
        $requirements_status = isset($account['requirements']['summary']['minimum_deadline']['status'])
            ? sanitize_key((string) $account['requirements']['summary']['minimum_deadline']['status'])
            : '';

        $charges_enabled = $transfer_status === 'active';
        $payouts_enabled = $transfer_status === 'active';
        $details_submitted = !in_array($requirements_status, ['currently_due', 'past_due'], true);
        $requirements_due = isset($account['requirements']['currently_due']) && is_array($account['requirements']['currently_due'])
            ? $account['requirements']['currently_due']
            : [];
        $requirements_due_count = count($requirements_due);
        if ($requirements_due_count === 0 && in_array($requirements_status, ['currently_due', 'past_due'], true)) {
            $requirements_due_count = 1;
        }
    } else {
        $charges_enabled = !empty($account['charges_enabled']);
        $payouts_enabled = !empty($account['payouts_enabled']);
        $details_submitted = !empty($account['details_submitted']);
        $requirements_due = isset($account['requirements']['currently_due']) && is_array($account['requirements']['currently_due'])
            ? $account['requirements']['currently_due']
            : [];
        $requirements_due_count = count($requirements_due);
    }

    $payout_interval = 'daily';
    if (isset($account['settings']['payouts']['schedule']['interval'])) {
        $payout_interval = gb_normalize_payout_interval($account['settings']['payouts']['schedule']['interval']);
    } elseif (isset($account['balance_settings']['payments']['payouts']['schedule']['interval'])) {
        $payout_interval = gb_normalize_payout_interval($account['balance_settings']['payments']['payouts']['schedule']['interval']);
    } else {
        $payout_interval = gb_normalize_payout_interval(get_post_meta($cleaner_id, '_gb_stripe_payout_interval', true));
    }

    update_post_meta($cleaner_id, '_gb_stripe_account_id', sanitize_text_field($account['id']));
    update_post_meta($cleaner_id, '_gb_stripe_charges_enabled', $charges_enabled ? '1' : '0');
    update_post_meta($cleaner_id, '_gb_stripe_payouts_enabled', $payouts_enabled ? '1' : '0');
    update_post_meta($cleaner_id, '_gb_stripe_details_submitted', $details_submitted ? '1' : '0');
    update_post_meta($cleaner_id, '_gb_stripe_requirements_due_count', (int) $requirements_due_count);
    update_post_meta($cleaner_id, '_gb_stripe_requirements_due_json', wp_json_encode($requirements_due));
    update_post_meta($cleaner_id, '_gb_stripe_payout_interval', $payout_interval);
    $country = isset($account['country']) ? $account['country'] : (isset($account['identity']['country']) ? $account['identity']['country'] : gb_get_connect_country_code());
    update_post_meta($cleaner_id, '_gb_stripe_country', sanitize_text_field((string) $country));
    update_post_meta(
        $cleaner_id,
        '_gb_stripe_connect_status',
        gb_cleaner_connect_status_label($charges_enabled, $payouts_enabled, $details_submitted, $requirements_due_count)
    );
    update_post_meta($cleaner_id, '_gb_stripe_last_sync', current_time('mysql'));
}

function gb_update_connected_account_payout_schedule($account_id, $interval) {
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Missing connected account ID.');
    }

    $interval = gb_normalize_payout_interval($interval);
    $params = ['settings[payouts][schedule][interval]' => $interval];
    if ($interval === 'weekly') {
        $params['settings[payouts][schedule][weekly_anchor]'] = 'monday';
    } elseif ($interval === 'monthly') {
        $params['settings[payouts][schedule][monthly_anchor]'] = 1;
    }

    return gb_stripe_api_request('accounts/' . rawurlencode($account_id), $params, 'POST');
}

function gb_sync_cleaner_connect_account($cleaner_id) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return new WP_Error('gb_invalid_cleaner', 'Invalid cleaner profile.');
    }

    $account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Cleaner has no Stripe Connect account yet.');
    }

    $account = gb_get_connect_account_from_stripe($account_id);
    if (is_wp_error($account)) {
        update_post_meta($cleaner_id, '_gb_stripe_last_error', $account->get_error_message());
        return $account;
    }

    gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account);
    delete_post_meta($cleaner_id, '_gb_stripe_last_error');

    return $account;
}

function gb_create_connect_account_for_cleaner($cleaner_id) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return new WP_Error('gb_invalid_cleaner', 'Invalid cleaner profile.');
    }

    $existing = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($existing !== '') {
        return ['id' => $existing];
    }

    $cleaner_post = get_post($cleaner_id);
    $owner = $cleaner_post ? get_user_by('id', (int) $cleaner_post->post_author) : null;
    $owner_email = $owner ? sanitize_email($owner->user_email) : '';
    $display_name = $cleaner_post ? sanitize_text_field($cleaner_post->post_title) : ('Cleaner ' . $cleaner_id);
    if ($display_name === '') {
        $display_name = 'Cleaner ' . $cleaner_id;
    }
    $payout_interval = gb_normalize_payout_interval(get_post_meta($cleaner_id, '_gb_stripe_payout_interval', true));
    $country_code = strtolower(gb_get_connect_country_code());

    /**
     * Connect v2 account creation (Stripe prompt-aligned):
     * - No top-level "type"
     * - dashboard="express"
     * - platform is responsible for fees/losses
     */
    $v2_payload = [
        'display_name' => $display_name,
        'identity' => [
            'country' => $country_code,
        ],
        'dashboard' => 'express',
        'defaults' => [
            'responsibilities' => [
                'fees_collector' => 'application',
                'losses_collector' => 'application',
            ],
        ],
        'configuration' => [
            'recipient' => [
                'capabilities' => [
                    'stripe_balance' => [
                        'stripe_transfers' => [
                            'requested' => true,
                        ],
                    ],
                ],
            ],
        ],
    ];
    if ($owner_email !== '') {
        $v2_payload['contact_email'] = $owner_email;
    }

    $account = gb_stripe_api_request_v2('core/accounts', $v2_payload, 'POST');

    // Compatibility fallback if v2 is not enabled yet on the Stripe account.
    if (is_wp_error($account)) {
        $legacy_params = [
            'type'                                    => 'express',
            'country'                                 => strtoupper($country_code),
            'business_type'                           => 'individual',
            'capabilities[card_payments][requested]'  => 'true',
            'capabilities[transfers][requested]'      => 'true',
            'metadata[cleaner_id]'                    => (string) $cleaner_id,
            'settings[payouts][schedule][interval]'   => $payout_interval,
        ];
        if ($owner_email !== '') {
            $legacy_params['email'] = $owner_email;
        }
        $account = gb_stripe_api_request('accounts', $legacy_params, 'POST');
    }

    if (is_wp_error($account)) {
        update_post_meta($cleaner_id, '_gb_stripe_last_error', $account->get_error_message());
        return $account;
    }

    $account = gb_stripe_unwrap_data_object($account);
    if (empty($account['id'])) {
        return new WP_Error('gb_connect_create_failed', 'Stripe did not return a connected account ID.');
    }

    update_post_meta($cleaner_id, '_gb_stripe_account_id', sanitize_text_field($account['id']));
    gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account);

    // Keep payouts aligned with manager preference once account exists.
    $schedule_sync = gb_update_connected_account_payout_schedule($account['id'], $payout_interval);
    if (is_wp_error($schedule_sync)) {
        update_post_meta($cleaner_id, '_gb_stripe_last_error', $schedule_sync->get_error_message());
    } else {
        delete_post_meta($cleaner_id, '_gb_stripe_last_error');
    }

    return $account;
}

function gb_connect_user_can_manage_cleaner($cleaner_id, $user) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return false;
    }

    if (current_user_can('administrator') || in_array('gb_manager', (array) $user->roles, true)) {
        return true;
    }

    if (in_array('gb_cleaner', (array) $user->roles, true)) {
        $cleaner_post = get_post($cleaner_id);
        return $cleaner_post && (int) $cleaner_post->post_author === (int) $user->ID;
    }

    return false;
}

function gb_connect_default_redirect_url($cleaner_id, $user) {
    if (current_user_can('administrator') || in_array('gb_manager', (array) $user->roles, true)) {
        return home_url('/manager-dashboard?tab=cleaners&action=edit&id=' . absint($cleaner_id));
    }
    return home_url('/cleaner-dashboard');
}

function gb_connect_redirect_with_notice($redirect_to, $status, $message = '') {
    $redirect_to = esc_url_raw($redirect_to ?: home_url('/cleaner-dashboard'));
    $args = ['connect' => sanitize_key($status)];
    if ($message !== '') {
        $args['connect_msg'] = sanitize_text_field($message);
    }
    wp_safe_redirect(add_query_arg($args, $redirect_to));
    exit;
}

function gb_handle_connect_onboarding() {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    $cleaner_id = isset($_GET['cleaner_id']) ? absint($_GET['cleaner_id']) : 0;
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';

    if ($redirect_to === '') {
        $redirect_to = gb_connect_default_redirect_url($cleaner_id, $user);
    }

    if (!$cleaner_id || !gb_connect_user_can_manage_cleaner($cleaner_id, $user)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'You do not have permission to manage Stripe Connect for this cleaner.');
    }

    if (
        empty($_GET['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gb_connect_onboarding_' . $cleaner_id)
    ) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Security check failed for Stripe Connect onboarding.');
    }

    $account = gb_create_connect_account_for_cleaner($cleaner_id);
    if (is_wp_error($account)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', $account->get_error_message());
    }

    $account_id = sanitize_text_field((string) ($account['id'] ?? get_post_meta($cleaner_id, '_gb_stripe_account_id', true)));
    if ($account_id === '') {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Unable to determine connected account ID.');
    }

    // Where Stripe sends them if the onboarding link expires - so it has to
    // avoid wp-admin for the same reason the buttons do.
    $refresh_url = wp_nonce_url(
        gb_connect_action_url('gb_connect_onboarding', $cleaner_id, $redirect_to),
        'gb_connect_onboarding_' . $cleaner_id
    );
    $return_url = add_query_arg('connect', 'returned', $redirect_to);

    $link = gb_stripe_api_request_v2('core/account_links', [
        'account' => $account_id,
        'use_case' => [
            'type' => 'account_onboarding',
            'account_onboarding' => [
                'configurations' => ['recipient'],
                'refresh_url' => $refresh_url,
                'return_url' => $return_url,
            ],
        ],
    ], 'POST');

    if (is_wp_error($link)) {
        // Backward-compatible fallback to v1 account links.
        $link = gb_stripe_api_request('account_links', [
            'account'     => $account_id,
            'refresh_url' => $refresh_url,
            'return_url'  => $return_url,
            'type'        => 'account_onboarding',
        ], 'POST');
    }

    if (!is_wp_error($link)) {
        $link = gb_stripe_unwrap_data_object($link);
    }

    if (is_wp_error($link) || empty($link['url'])) {
        $msg = is_wp_error($link) ? $link->get_error_message() : 'Stripe did not return an onboarding link.';
        gb_connect_redirect_with_notice($redirect_to, 'error', $msg);
    }

    wp_safe_redirect(esc_url_raw($link['url']));
    exit;
}
/**
 * Build a Connect action link that does not go through wp-admin.
 *
 * The three Connect buttons posted to admin-post.php, and this site redirects
 * anyone who is not an administrator away from /wp-admin/ - so a cleaner
 * pressing "Set up payouts" on their own settings page was bounced back to
 * their dashboard before the handler ever ran, silently, with no error. The
 * feature is built for cleaners to use on themselves, and it has never worked
 * for its main user. Managers hit the same wall.
 *
 * Rather than loosen that lockout, the links come in through the front end,
 * the same way membership checkout does. Every handler still checks login,
 * ownership of the cleaner, and the nonce for itself, so nothing about who is
 * allowed to do what has changed here - only the door they come through.
 */
function gb_connect_action_url($action, $cleaner_id, $redirect_to) {
    return add_query_arg(
        [
            'gb_connect_action' => $action,
            'cleaner_id'        => (int) $cleaner_id,
            'redirect_to'       => $redirect_to,
        ],
        home_url('/')
    );
}

function gb_connect_handle_front_end_request() {
    if (empty($_GET['gb_connect_action'])) {
        return;
    }

    $handlers = [
        'gb_connect_onboarding' => 'gb_handle_connect_onboarding',
        'gb_connect_login_link' => 'gb_handle_connect_login_link',
        'gb_connect_sync'       => 'gb_handle_connect_sync',
    ];

    $action = sanitize_key(wp_unslash($_GET['gb_connect_action']));

    if (!isset($handlers[$action]) || !function_exists($handlers[$action])) {
        return;
    }

    // These end in a redirect to Stripe or back to a dashboard; none of it
    // should ever be served from a page cache.
    nocache_headers();

    call_user_func($handlers[$action]);
}
add_action('init', 'gb_connect_handle_front_end_request');

add_action('admin_post_gb_connect_onboarding', 'gb_handle_connect_onboarding');

function gb_handle_connect_login_link() {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    $cleaner_id = isset($_GET['cleaner_id']) ? absint($_GET['cleaner_id']) : 0;
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
    if ($redirect_to === '') {
        $redirect_to = gb_connect_default_redirect_url($cleaner_id, $user);
    }

    if (!$cleaner_id || !gb_connect_user_can_manage_cleaner($cleaner_id, $user)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'You do not have permission to access this Stripe Connect account.');
    }

    if (
        empty($_GET['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gb_connect_login_' . $cleaner_id)
    ) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Security check failed for Stripe Connect dashboard access.');
    }

    $account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($account_id === '') {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Cleaner is not connected to Stripe yet.');
    }

    $login_link = gb_stripe_api_request('accounts/' . rawurlencode($account_id) . '/login_links', [], 'POST');
    if (is_wp_error($login_link) || empty($login_link['url'])) {
        $msg = is_wp_error($login_link) ? $login_link->get_error_message() : 'Stripe did not return an Express dashboard link.';
        gb_connect_redirect_with_notice($redirect_to, 'error', $msg);
    }

    wp_safe_redirect(esc_url_raw($login_link['url']));
    exit;
}
add_action('admin_post_gb_connect_login_link', 'gb_handle_connect_login_link');

function gb_handle_connect_sync() {
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    $cleaner_id = isset($_GET['cleaner_id']) ? absint($_GET['cleaner_id']) : 0;
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
    if ($redirect_to === '') {
        $redirect_to = gb_connect_default_redirect_url($cleaner_id, $user);
    }

    if (!$cleaner_id || !gb_connect_user_can_manage_cleaner($cleaner_id, $user)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'You do not have permission to sync this Stripe Connect account.');
    }

    if (
        empty($_GET['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'gb_connect_sync_' . $cleaner_id)
    ) {
        gb_connect_redirect_with_notice($redirect_to, 'error', 'Security check failed for Stripe Connect sync.');
    }

    $synced = gb_sync_cleaner_connect_account($cleaner_id);
    if (is_wp_error($synced)) {
        gb_connect_redirect_with_notice($redirect_to, 'error', $synced->get_error_message());
    }

    gb_connect_redirect_with_notice($redirect_to, 'synced', 'Stripe Connect status refreshed.');
}
add_action('admin_post_gb_connect_sync', 'gb_handle_connect_sync');

/**
 * Retrieve Global Stripe Configuration
 * Call this anywhere in the theme to get current keys.
 */
function gb_stripe_value_is_placeholder($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return true;
    }

    $markers = ['***', 'your_', 'placeholder', 'change_me', 'replace_me', 'example'];
    $lower = strtolower($value);
    foreach ($markers as $marker) {
        if (strpos($lower, $marker) !== false) {
            return true;
        }
    }

    return false;
}

function gb_get_stripe_config_issues($config, $required_fields = ['secret_key']) {
    $required_fields = is_array($required_fields) ? $required_fields : ['secret_key'];
    $issues = [];

    foreach ($required_fields as $field_key) {
        $value = isset($config[$field_key]) ? (string) $config[$field_key] : '';
        if (gb_stripe_value_is_placeholder($value)) {
            $issues[] = $field_key;
        }
    }

    return $issues;
}

function gb_require_stripe_config($required_fields = ['secret_key']) {
    $config = gb_get_stripe_config();
    $issues = gb_get_stripe_config_issues($config, $required_fields);

    if (!empty($issues)) {
        $labels = [
            'publishable_key' => 'publishable key',
            'secret_key' => 'secret key',
            'webhook_secret' => 'webhook secret',
            'connect_webhook_secret' => 'Connect webhook secret',
        ];
        $missing = [];
        foreach ($issues as $issue) {
            $missing[] = isset($labels[$issue]) ? $labels[$issue] : $issue;
        }

        return new WP_Error(
            'gb_stripe_missing_config',
            'Stripe ' . implode(', ', $missing) . ' is missing. Add it in Manager Dashboard > Settings > Stripe Connect Credentials.'
        );
    }

    return $config;
}

function gb_get_stripe_config() {
    $settings_id = get_option('page_on_front'); // Data is stored on Front Page ID

    $mode = get_post_meta($settings_id, '_gb_stripe_mode', true) ?: 'test';
    if (!in_array($mode, ['test', 'live'], true)) {
        $mode = 'test';
    }

    $publishable_key = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_publishable_key', true)
        : get_post_meta($settings_id, '_gb_stripe_test_publishable_key', true);

    $secret_key = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_secret_key', true)
        : get_post_meta($settings_id, '_gb_stripe_test_secret_key', true);

    $webhook_secret = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_webhook_secret', true)
        : get_post_meta($settings_id, '_gb_stripe_test_webhook_secret', true);
    $connect_webhook_secret = $mode === 'live'
        ? get_post_meta($settings_id, '_gb_stripe_live_connect_webhook_secret', true)
        : get_post_meta($settings_id, '_gb_stripe_test_connect_webhook_secret', true);

    // Backward compatibility with legacy single-key fields.
    if (empty($publishable_key)) {
        $publishable_key = get_post_meta($settings_id, '_gb_stripe_publishable_key', true);
    }
    if (empty($secret_key)) {
        $secret_key = get_post_meta($settings_id, '_gb_stripe_secret_key', true);
    }
    if (empty($webhook_secret)) {
        $webhook_secret = get_post_meta($settings_id, '_gb_stripe_webhook_secret', true);
    }
    if (empty($connect_webhook_secret)) {
        $connect_webhook_secret = get_post_meta($settings_id, '_gb_stripe_connect_webhook_secret', true);
    }

    return [
        'mode'            => $mode,
        'publishable_key' => trim((string) $publishable_key),
        'secret_key'      => trim((string) $secret_key),
        'webhook_secret'  => trim((string) $webhook_secret),
        'connect_webhook_secret' => trim((string) $connect_webhook_secret),
        'platform_fee'    => get_post_meta($settings_id, '_gb_platform_fee', true) !== '' ? get_post_meta($settings_id, '_gb_platform_fee', true) : '0',
        'webhook_url'     => gb_get_stripe_webhook_url(),
    ];
}

function gb_stripe_api_request($endpoint, $params = [], $method = 'POST') {
    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        return $config;
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $method = strtoupper($method);
    $request_args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $config['secret_key'],
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
    ];

    if (!empty($params) && $method === 'GET') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params, '', '&');
    } elseif (!empty($params)) {
        $request_args['body'] = http_build_query($params, '', '&');
    }

    $response = wp_remote_request($url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status >= 400) {
        $error_message = 'Stripe request failed.';
        if (is_array($data) && isset($data['error']['message'])) {
            $error_message = $data['error']['message'];
        }
        return new WP_Error('gb_stripe_api_error', $error_message, ['status' => $status, 'payload' => $data]);
    }

    if (!is_array($data)) {
        return new WP_Error('gb_stripe_invalid_response', 'Stripe returned an invalid response.');
    }

    return $data;
}

/**
 * Lightweight Stripe v2 request helper.
 * This keeps the integration ready for Connect v2 account and thin-event workflows.
 */
function gb_stripe_api_request_v2($endpoint, $params = [], $method = 'POST') {
    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        return $config;
    }

    $url = 'https://api.stripe.com/v2/' . ltrim($endpoint, '/');
    $method = strtoupper($method);
    $request_args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $config['secret_key'],
            'Content-Type'  => 'application/json',
        ],
    ];

    if (!empty($params) && $method === 'GET') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params, '', '&');
    } elseif (!empty($params)) {
        $request_args['body'] = wp_json_encode($params);
    }

    $response = wp_remote_request($url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status >= 400) {
        $error_message = 'Stripe v2 request failed.';
        if (is_array($data) && isset($data['error']['message'])) {
            $error_message = $data['error']['message'];
        }
        return new WP_Error('gb_stripe_v2_api_error', $error_message, ['status' => $status, 'payload' => $data]);
    }

    if (!is_array($data)) {
        return new WP_Error('gb_stripe_v2_invalid_response', 'Stripe v2 returned an invalid response.');
    }

    return $data;
}

function gb_stripe_verify_signature($payload, $signature_header, $secret, $tolerance = 300) {
    if (empty($payload) || empty($signature_header) || empty($secret)) {
        return false;
    }

    $timestamp = null;
    $signatures = [];
    $parts = explode(',', $signature_header);

    foreach ($parts as $part) {
        $segment = explode('=', trim($part), 2);
        if (count($segment) !== 2) {
            continue;
        }
        if ($segment[0] === 't') {
            $timestamp = (int) $segment[1];
        }
        if ($segment[0] === 'v1') {
            $signatures[] = $segment[1];
        }
    }

    if (!$timestamp || empty($signatures)) {
        return false;
    }

    if (abs(time() - $timestamp) > (int) $tolerance) {
        return false;
    }

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function gb_sync_booking_from_checkout_session($booking_id, $session_id) {
    $booking_id = absint($booking_id);
    $session_id = sanitize_text_field($session_id);

    if (!$booking_id || get_post_type($booking_id) !== 'gb_booking') {
        return new WP_Error('gb_invalid_booking', 'Invalid booking for Stripe sync.');
    }
    if (empty($session_id)) {
        return new WP_Error('gb_missing_session', 'Missing Stripe checkout session ID.');
    }

    $session = gb_stripe_api_request('checkout/sessions/' . rawurlencode($session_id), [], 'GET');
    if (is_wp_error($session)) {
        return $session;
    }

    $metadata_booking_id = isset($session['metadata']['booking_id']) ? absint($session['metadata']['booking_id']) : 0;
    if ($metadata_booking_id !== $booking_id) {
        return new WP_Error('gb_mismatch_booking', 'Stripe checkout session does not match this booking.');
    }

    $payment_status = isset($session['payment_status']) ? sanitize_text_field($session['payment_status']) : '';
    $payment_intent_id = isset($session['payment_intent']) ? sanitize_text_field($session['payment_intent']) : '';
    update_post_meta($booking_id, '_gb_payment_provider', 'stripe');
    update_post_meta($booking_id, '_gb_stripe_checkout_session_id', sanitize_text_field($session['id']));
    update_post_meta($booking_id, '_gb_payment_status', $payment_status ?: 'pending');
    update_post_meta($booking_id, '_gb_stripe_payment_intent', $payment_intent_id);

    if ($payment_intent_id !== '') {
        $payment_intent = gb_stripe_api_request('payment_intents/' . rawurlencode($payment_intent_id), [], 'GET');
        if (!is_wp_error($payment_intent) && is_array($payment_intent)) {
            $destination_account = isset($payment_intent['transfer_data']['destination'])
                ? sanitize_text_field($payment_intent['transfer_data']['destination'])
                : '';
            $application_fee_amount = isset($payment_intent['application_fee_amount'])
                ? (int) $payment_intent['application_fee_amount']
                : 0;
            $latest_charge = isset($payment_intent['latest_charge']) ? sanitize_text_field($payment_intent['latest_charge']) : '';

            if ($destination_account !== '') {
                update_post_meta($booking_id, '_gb_stripe_destination_account', $destination_account);
            }
            if ($application_fee_amount > 0) {
                update_post_meta($booking_id, '_gb_platform_fee_gbp', number_format($application_fee_amount / 100, 2, '.', ''));
            }
            if ($latest_charge !== '') {
                update_post_meta($booking_id, '_gb_stripe_charge_id', $latest_charge);
            }
        }
    }

    $booking_total = (float) get_post_meta($booking_id, '_gb_booking_total_gbp', true);
    $platform_fee = (float) get_post_meta($booking_id, '_gb_platform_fee_gbp', true);
    if ($booking_total > 0) {
        $cleaner_net = max(0, $booking_total - $platform_fee);
        update_post_meta($booking_id, '_gb_cleaner_payout_estimate_gbp', number_format($cleaner_net, 2, '.', ''));
    }

    if ($payment_status === 'paid') {
        $was_already_confirmed = get_post_meta($booking_id, '_gb_booking_status', true) === 'confirmed';
        wp_update_post(['ID' => $booking_id, 'post_status' => 'publish']);
        update_post_meta($booking_id, '_gb_booking_status', 'confirmed');
        if (!$was_already_confirmed) {
            gb_send_booking_confirmation_emails($booking_id);
        }
    } else {
        if (get_post_status($booking_id) !== 'pending') {
            wp_update_post(['ID' => $booking_id, 'post_status' => 'pending']);
        }
        if (!get_post_meta($booking_id, '_gb_booking_status', true)) {
            update_post_meta($booking_id, '_gb_booking_status', 'pending_payment');
        }
    }

    return $session;
}

function gb_send_booking_confirmation_emails($booking_id) {
    $booking_id = absint($booking_id);
    if (!$booking_id || get_post_type($booking_id) !== 'gb_booking') {
        return;
    }

    $customer_name    = (string) get_post_meta($booking_id, '_gb_customer_name', true);
    $customer_email   = sanitize_email((string) get_post_meta($booking_id, '_gb_customer_email', true));
    $service          = (string) get_post_meta($booking_id, '_gb_booking_service', true);
    $date             = (string) get_post_meta($booking_id, '_gb_booking_date', true);
    $time             = (string) get_post_meta($booking_id, '_gb_booking_time', true);
    $duration         = (int) get_post_meta($booking_id, '_gb_booking_duration', true);
    $address          = (string) get_post_meta($booking_id, '_gb_customer_address', true);
    $total_gbp        = (float) get_post_meta($booking_id, '_gb_booking_total_gbp', true);
    $cleaner_post_id  = (int) get_post_meta($booking_id, '_gb_booking_cleaner_id', true);
    $cleaner_name     = $cleaner_post_id ? get_the_title($cleaner_post_id) : 'TBC';
    $cleaner_user_id  = $cleaner_post_id ? (int) get_post_field('post_author', $cleaner_post_id) : 0;
    $access_token     = (string) get_post_meta($booking_id, '_gb_booking_access_token_raw', true);

    $site_name  = get_bloginfo('name');
    $date_fmt   = $date ? date_i18n('l j F Y', strtotime($date)) : $date;
    $time_fmt   = $time ? date_i18n('g:i A', strtotime($time)) : $time;
    $total_fmt  = $total_gbp > 0 ? '£' . number_format($total_gbp, 2) : '';
    $booking_url = add_query_arg(
        ['bid' => $booking_id, 't' => gb_generate_booking_access_token($booking_id)],
        home_url('/booking-success')
    );

    // — Customer email —
    if (is_email($customer_email)) {
        $subject = sprintf('[%s] Your booking is confirmed — %s on %s', $site_name, $service, $date_fmt);
        $body    = "Hi {$customer_name},\n\nYour booking is confirmed. Here are the details:\n\n"
                 . "Service:      {$service}\n"
                 . "Professional: {$cleaner_name}\n"
                 . "Date:         {$date_fmt}\n"
                 . "Time:         {$time_fmt}\n"
                 . "Duration:     {$duration} hour" . ($duration === 1 ? '' : 's') . "\n"
                 . "Address:      {$address}\n"
                 . ($total_fmt ? "Total paid:   {$total_fmt}\n" : '')
                 . "\nView your booking: {$booking_url}\n\n"
                 . "Thank you for booking with {$site_name}.";
        $customer_duration = $duration > 0
            ? $duration . ' hour' . ($duration === 1 ? '' : 's')
            : '';
        gb_send_brevo_template_email(
            $customer_email,
            $subject,
            $body,
            [],
            gb_get_brevo_template_id( 'booking_customer' ),
            [
                'first_name'         => gb_brevo_first_name( $customer_name ),
                'booking_url'        => $booking_url,
                'service'            => $service,
                'professional_name'  => $cleaner_name,
                'date'               => $date_fmt,
                'time'               => $time_fmt,
                'duration'           => $customer_duration,
                'address'            => $address,
                'total'              => $total_fmt,
            ]
        );
    }

    // — Cleaner email —
    if ($cleaner_user_id > 0) {
        $cleaner_user = get_userdata($cleaner_user_id);
        if ($cleaner_user && is_email($cleaner_user->user_email)) {
            $subject = sprintf('[%s] New booking confirmed — %s on %s', $site_name, $service, $date_fmt);
            $body    = "Hi {$cleaner_name},\n\nYou have a new confirmed booking:\n\n"
                     . "Service:  {$service}\n"
                     . "Customer: {$customer_name}\n"
                     . "Date:     {$date_fmt}\n"
                     . "Time:     {$time_fmt}\n"
                     . "Duration: {$duration} hour" . ($duration === 1 ? '' : 's') . "\n"
                     . "Address:  {$address}\n\n"
                     . "Log in to your dashboard to view full details: " . home_url('/cleaner-dashboard') . "\n\n"
                     . $site_name;
            $cleaner_duration = $duration > 0
                ? $duration . ' hour' . ($duration === 1 ? '' : 's')
                : '';
            gb_send_brevo_template_email(
                $cleaner_user->user_email,
                $subject,
                $body,
                [],
                gb_get_brevo_template_id( 'booking_cleaner' ),
                [
                    'first_name'     => gb_brevo_first_name( $cleaner_name ),
                    'booking_url'    => home_url( '/cleaner-dashboard' ),
                    'service'        => $service,
                    'customer_name'  => $customer_name,
                    'date'           => $date_fmt,
                    'time'           => $time_fmt,
                    'duration'       => $cleaner_duration,
                    'address'        => $address,
                ]
            );
        }
    }
}

function gb_get_cleaner_connect_status_live($cleaner_id) {
    $cleaner_id = absint($cleaner_id);
    if (!$cleaner_id || get_post_type($cleaner_id) !== 'gb_cleaner') {
        return new WP_Error('gb_invalid_cleaner', 'Invalid cleaner profile.');
    }

    $account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    if ($account_id === '') {
        return new WP_Error('gb_missing_connect_account', 'Cleaner has no Stripe Connect account yet.');
    }

    $account = gb_get_connect_account_from_stripe($account_id);
    if (is_wp_error($account)) {
        return $account;
    }

    gb_sync_cleaner_connect_meta_from_account($cleaner_id, $account);
    return gb_stripe_unwrap_data_object($account);
}

function gb_get_service_storefront_currency($service_id = 0) {
    $service_id = absint($service_id);
    $currency = $service_id ? sanitize_key((string) get_post_meta($service_id, '_gb_service_currency', true)) : '';
    if ($currency === '') {
        $currency = 'gbp';
    }
    return in_array($currency, ['gbp', 'usd', 'eur'], true) ? $currency : 'gbp';
}

function gb_get_service_storefront_amount_minor($service_id) {
    $service_id = absint($service_id);
    $price = (float) get_post_meta($service_id, 'service_price', true);
    // Keep Stripe prices valid even when surcharge is zero on booking logic.
    return max(50, (int) round(max(0, $price) * 100));
}

function gb_get_service_destination_account_id($service_id) {
    $service_id = absint($service_id);
    if (!$service_id || get_post_type($service_id) !== 'gb_service') {
        return '';
    }

    $cleaner_id = absint(get_post_meta($service_id, '_gb_service_destination_cleaner_id', true));
    if (!$cleaner_id) {
        return '';
    }

    return sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
}

function gb_sync_service_to_stripe_product($service_id, $force_price_refresh = false) {
    $service_id = absint($service_id);
    if (!$service_id || get_post_type($service_id) !== 'gb_service') {
        return new WP_Error('gb_invalid_service', 'Invalid service for Stripe product sync.');
    }

    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        update_post_meta($service_id, '_gb_stripe_product_last_error', $config->get_error_message());
        return $config;
    }

    $service_post = get_post($service_id);
    if (!$service_post) {
        return new WP_Error('gb_missing_service_post', 'Service post not found.');
    }

    $name = sanitize_text_field((string) $service_post->post_title);
    $description = sanitize_textarea_field((string) $service_post->post_content);
    $active = get_post_status($service_id) === 'publish';
    $currency = gb_get_service_storefront_currency($service_id);
    $amount_minor = gb_get_service_storefront_amount_minor($service_id);

    $existing_product_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_product_id', true));
    $existing_price_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_price_id', true));
    $last_amount_minor = (int) get_post_meta($service_id, '_gb_stripe_product_amount_minor', true);
    $last_currency = sanitize_key((string) get_post_meta($service_id, '_gb_stripe_product_currency', true));

    $product_payload = [
        'name' => $name !== '' ? $name : ('Service ' . $service_id),
        'description' => $description,
        'active' => $active ? 'true' : 'false',
        'metadata[gb_service_id]' => (string) $service_id,
        'metadata[gb_service_slug]' => sanitize_title((string) $service_post->post_name),
        'metadata[managed_from]' => 'manager_services_tab',
    ];

    if ($existing_product_id === '') {
        $create_payload = $product_payload;
        $create_payload['default_price_data[unit_amount]'] = $amount_minor;
        $create_payload['default_price_data[currency]'] = $currency;
        $product = gb_stripe_api_request('products', $create_payload, 'POST');
        if (is_wp_error($product) || empty($product['id'])) {
            $msg = is_wp_error($product) ? $product->get_error_message() : 'Stripe did not return a product ID.';
            update_post_meta($service_id, '_gb_stripe_product_last_error', $msg);
            return new WP_Error('gb_service_product_create_failed', $msg);
        }

        $existing_product_id = sanitize_text_field($product['id']);
        update_post_meta($service_id, '_gb_stripe_product_id', $existing_product_id);

        if (!empty($product['default_price']) && is_string($product['default_price'])) {
            $existing_price_id = sanitize_text_field($product['default_price']);
            update_post_meta($service_id, '_gb_stripe_price_id', $existing_price_id);
        }
    } else {
        $update_product = gb_stripe_api_request('products/' . rawurlencode($existing_product_id), $product_payload, 'POST');
        if (is_wp_error($update_product)) {
            update_post_meta($service_id, '_gb_stripe_product_last_error', $update_product->get_error_message());
            return $update_product;
        }
    }

    $needs_new_price = $force_price_refresh || $existing_price_id === '' || $last_amount_minor !== $amount_minor || $last_currency !== $currency;
    if ($needs_new_price) {
        $new_price = gb_stripe_api_request('prices', [
            'unit_amount' => $amount_minor,
            'currency' => $currency,
            'product' => $existing_product_id,
        ], 'POST');
        if (is_wp_error($new_price) || empty($new_price['id'])) {
            $msg = is_wp_error($new_price) ? $new_price->get_error_message() : 'Stripe did not return a price ID.';
            update_post_meta($service_id, '_gb_stripe_product_last_error', $msg);
            return new WP_Error('gb_service_price_create_failed', $msg);
        }

        $existing_price_id = sanitize_text_field($new_price['id']);
        update_post_meta($service_id, '_gb_stripe_price_id', $existing_price_id);

        $set_default = gb_stripe_api_request('products/' . rawurlencode($existing_product_id), [
            'default_price' => $existing_price_id,
        ], 'POST');
        if (is_wp_error($set_default)) {
            update_post_meta($service_id, '_gb_stripe_product_last_error', $set_default->get_error_message());
            return $set_default;
        }
    }

    update_post_meta($service_id, '_gb_stripe_product_amount_minor', $amount_minor);
    update_post_meta($service_id, '_gb_stripe_product_currency', $currency);
    update_post_meta($service_id, '_gb_stripe_product_last_sync', current_time('mysql'));
    delete_post_meta($service_id, '_gb_stripe_product_last_error');

    $destination_account_id = gb_get_service_destination_account_id($service_id);
    $destination_cleaner_id = absint(get_post_meta($service_id, '_gb_service_destination_cleaner_id', true));
    if ($existing_product_id !== '') {
        gb_set_stripe_storefront_mapping($existing_product_id, [
            'connected_account_id' => $destination_account_id,
            'cleaner_id' => $destination_cleaner_id,
            'created_at' => current_time('mysql'),
        ]);
    }

    return [
        'product_id' => $existing_product_id,
        'price_id' => $existing_price_id,
        'amount_minor' => $amount_minor,
        'currency' => $currency,
        'active' => $active,
    ];
}

function gb_archive_service_stripe_product($service_id) {
    $service_id = absint($service_id);
    if (!$service_id || get_post_type($service_id) !== 'gb_service') {
        return new WP_Error('gb_invalid_service', 'Invalid service for Stripe archive.');
    }

    $product_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_product_id', true));
    if ($product_id === '') {
        return true;
    }

    $result = gb_stripe_api_request('products/' . rawurlencode($product_id), ['active' => 'false'], 'POST');
    if (is_wp_error($result)) {
        update_post_meta($service_id, '_gb_stripe_product_last_error', $result->get_error_message());
        return $result;
    }

    update_post_meta($service_id, '_gb_stripe_product_last_sync', current_time('mysql'));
    return true;
}

function gb_get_stripe_storefront_mappings() {
    $mappings = get_option('gb_stripe_product_mappings', []);
    return is_array($mappings) ? $mappings : [];
}

function gb_set_stripe_storefront_mapping($product_id, $mapping) {
    $product_id = sanitize_text_field((string) $product_id);
    if ($product_id === '') {
        return;
    }

    $mappings = gb_get_stripe_storefront_mappings();
    $mappings[$product_id] = [
        'connected_account_id' => sanitize_text_field((string) ($mapping['connected_account_id'] ?? '')),
        'cleaner_id' => absint($mapping['cleaner_id'] ?? 0),
        'created_at' => sanitize_text_field((string) ($mapping['created_at'] ?? current_time('mysql'))),
    ];
    update_option('gb_stripe_product_mappings', $mappings, false);
}

function gb_get_stripe_storefront_mapping($product_id) {
    $product_id = sanitize_text_field((string) $product_id);
    if ($product_id === '') {
        return [];
    }

    $mappings = gb_get_stripe_storefront_mappings();
    return isset($mappings[$product_id]) && is_array($mappings[$product_id]) ? $mappings[$product_id] : [];
}

function gb_get_manager_settings_url($args = []) {
    $base = home_url('/manager-dashboard');
    $defaults = ['tab' => 'settings'];
    return add_query_arg(array_merge($defaults, is_array($args) ? $args : []), $base);
}

function gb_redirect_manager_settings_with_stripe_notice($status, $message) {
    $status = $status === 'ok' ? 'ok' : 'error';
    $message = sanitize_text_field((string) $message);
    wp_safe_redirect(gb_get_manager_settings_url(['stripe_notice' => $status, 'stripe_msg' => $message]));
    exit;
}

function gb_handle_stripe_sample_create_product() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    $user = wp_get_current_user();
    if (!current_user_can('administrator') && !in_array('gb_manager', (array) $user->roles, true)) {
        wp_die('You do not have permission to create Stripe products.');
    }

    if (
        empty($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gb_stripe_sample_create_product')
    ) {
        wp_die('Security check failed.');
    }

    $config = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($config)) {
        gb_redirect_manager_settings_with_stripe_notice('error', $config->get_error_message());
    }

    $name = isset($_POST['gb_product_name']) ? sanitize_text_field(wp_unslash($_POST['gb_product_name'])) : '';
    $description = isset($_POST['gb_product_description']) ? sanitize_textarea_field(wp_unslash($_POST['gb_product_description'])) : '';
    $unit_amount = isset($_POST['gb_product_amount']) ? absint($_POST['gb_product_amount']) : 0;
    $currency = isset($_POST['gb_product_currency']) ? strtolower(sanitize_key(wp_unslash($_POST['gb_product_currency']))) : 'gbp';
    $cleaner_id = isset($_POST['gb_product_cleaner_id']) ? absint($_POST['gb_product_cleaner_id']) : 0;

    if ($name === '' || $unit_amount < 50) {
        gb_redirect_manager_settings_with_stripe_notice('error', 'Product name is required and amount must be at least 50 (minor units).');
    }

    if (!in_array($currency, ['gbp', 'usd', 'eur'], true)) {
        $currency = 'gbp';
    }

    $connected_account_id = '';
    if ($cleaner_id) {
        $connected_account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
        if ($connected_account_id === '') {
            gb_redirect_manager_settings_with_stripe_notice('error', 'Selected cleaner has no connected Stripe account yet.');
        }
    }

    // Create product on the platform account (never on connected accounts).
    $product = gb_stripe_api_request('products', [
        'name' => $name,
        'description' => $description,
        'default_price_data[unit_amount]' => $unit_amount,
        'default_price_data[currency]' => $currency,
        'metadata[sample_storefront]' => '1',
    ], 'POST');
    if (is_wp_error($product) || empty($product['id'])) {
        $msg = is_wp_error($product) ? $product->get_error_message() : 'Stripe did not return a product ID.';
        gb_redirect_manager_settings_with_stripe_notice('error', $msg);
    }

    gb_set_stripe_storefront_mapping($product['id'], [
        'connected_account_id' => $connected_account_id,
        'cleaner_id' => $cleaner_id,
        'created_at' => current_time('mysql'),
    ]);

    gb_redirect_manager_settings_with_stripe_notice('ok', 'Stripe product created and mapped successfully.');
}
add_action('admin_post_gb_stripe_sample_create_product', 'gb_handle_stripe_sample_create_product');

function gb_get_stripe_platform_products($limit = 100) {
    $limit = max(1, min(100, absint($limit)));
    return gb_stripe_api_request('products', [
        'active' => 'true',
        'limit' => $limit,
        'expand' => ['data.default_price'],
    ], 'GET');
}

function gb_handle_stripe_storefront_checkout() {
    $referer = wp_get_referer();
    if (!$referer) {
        $referer = home_url('/');
    }

    if (
        empty($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'gb_stripe_storefront_checkout')
    ) {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'invalid_request', $referer));
        exit;
    }

    $product_id = isset($_POST['product_id']) ? sanitize_text_field(wp_unslash($_POST['product_id'])) : '';
    $quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;
    if ($product_id === '') {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'missing_product', $referer));
        exit;
    }

    $config = gb_require_stripe_config(['secret_key', 'publishable_key']);
    if (is_wp_error($config)) {
        wp_safe_redirect(add_query_arg('stripe_storefront', rawurlencode($config->get_error_message()), $referer));
        exit;
    }

    $mapping = gb_get_stripe_storefront_mapping($product_id);
    $destination_account = sanitize_text_field((string) ($mapping['connected_account_id'] ?? ''));
    if ($destination_account === '') {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'missing_destination', $referer));
        exit;
    }

    $product = gb_stripe_api_request(
        'products/' . rawurlencode($product_id),
        ['expand' => ['default_price']],
        'GET'
    );
    if (is_wp_error($product)) {
        wp_safe_redirect(add_query_arg('stripe_storefront', rawurlencode($product->get_error_message()), $referer));
        exit;
    }

    $default_price = isset($product['default_price']) && is_array($product['default_price']) ? $product['default_price'] : [];
    if (empty($default_price) && !empty($product['default_price'])) {
        $default_price = gb_stripe_api_request('prices/' . rawurlencode($product['default_price']), [], 'GET');
    }
    if (is_wp_error($default_price) || empty($default_price['id'])) {
        wp_safe_redirect(add_query_arg('stripe_storefront', 'missing_price', $referer));
        exit;
    }

    $unit_amount = isset($default_price['unit_amount']) ? (int) $default_price['unit_amount'] : 0;
    $gross_amount = max(0, $unit_amount * $quantity);
    $fee_percent = (float) ($config['platform_fee'] ?? 0);
    $application_fee_amount = (int) round($gross_amount * max(0, $fee_percent) / 100);

    // Destination charge with platform fee collection.
    $session = gb_stripe_api_request('checkout/sessions', [
        'mode' => 'payment',
        'line_items[0][price]' => $default_price['id'],
        'line_items[0][quantity]' => $quantity,
        'payment_intent_data[application_fee_amount]' => $application_fee_amount,
        'payment_intent_data[transfer_data][destination]' => $destination_account,
        'success_url' => add_query_arg('stripe_storefront', 'success', $referer),
        'cancel_url' => add_query_arg('stripe_storefront', 'cancelled', $referer),
    ], 'POST');

    if (is_wp_error($session) || empty($session['url'])) {
        $msg = is_wp_error($session) ? $session->get_error_message() : 'Checkout session URL missing.';
        wp_safe_redirect(add_query_arg('stripe_storefront', rawurlencode($msg), $referer));
        exit;
    }

    wp_safe_redirect(esc_url_raw($session['url']));
    exit;
}
add_action('admin_post_gb_stripe_storefront_checkout', 'gb_handle_stripe_storefront_checkout');
add_action('admin_post_nopriv_gb_stripe_storefront_checkout', 'gb_handle_stripe_storefront_checkout');

function gb_render_stripe_storefront_shortcode($atts = []) {
    $config = gb_require_stripe_config(['secret_key', 'publishable_key']);
    if (is_wp_error($config)) {
        return '<div style="padding:14px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; color:#991b1b;">' . esc_html($config->get_error_message()) . '</div>';
    }

    $products_response = gb_get_stripe_platform_products(100);
    if (is_wp_error($products_response)) {
        return '<div style="padding:14px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; color:#991b1b;">' . esc_html($products_response->get_error_message()) . '</div>';
    }

    $products = isset($products_response['data']) && is_array($products_response['data']) ? $products_response['data'] : [];
    $mappings = gb_get_stripe_storefront_mappings();

    $cleaners = get_posts([
        'post_type' => 'gb_cleaner',
        'post_status' => ['publish', 'pending', 'draft'],
        'numberposts' => -1,
    ]);
    $cleaner_accounts = [];
    foreach ($cleaners as $cleaner) {
        $account_id = sanitize_text_field((string) get_post_meta($cleaner->ID, '_gb_stripe_account_id', true));
        if ($account_id !== '') {
            $cleaner_accounts[] = [
                'name' => $cleaner->post_title,
                'account_id' => $account_id,
            ];
        }
    }

    ob_start();
    ?>
    <div style="border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:20px;">
        <h3 style="margin:0 0 6px; color:#0f172a;">Sample Storefront</h3>
        <p style="margin:0 0 18px; color:#64748b; font-size:13px;">All platform products are shown below. Each product can be mapped to a connected cleaner account for destination charges.</p>

        <?php
        $storefront_state = isset($_GET['stripe_storefront']) ? sanitize_text_field(wp_unslash($_GET['stripe_storefront'])) : '';
        if ($storefront_state === 'success'):
        ?>
            <div style="margin:0 0 14px; padding:10px 12px; background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; color:#166534; font-size:13px;">Payment completed successfully.</div>
        <?php elseif ($storefront_state !== '' && $storefront_state !== 'cancelled'): ?>
            <div style="margin:0 0 14px; padding:10px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; font-size:13px;">
                Storefront error: <?php echo esc_html(rawurldecode($storefront_state)); ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; gap:12px;">
            <?php if (empty($products)): ?>
                <div style="padding:12px; border:1px dashed #cbd5e1; border-radius:8px; color:#64748b;">No Stripe products found yet.</div>
            <?php endif; ?>
            <?php foreach ($products as $product):
                $product_id = sanitize_text_field((string) ($product['id'] ?? ''));
                if ($product_id === '') {
                    continue;
                }
                $mapping = isset($mappings[$product_id]) && is_array($mappings[$product_id]) ? $mappings[$product_id] : [];
                $destination_account = sanitize_text_field((string) ($mapping['connected_account_id'] ?? ''));
                $default_price = isset($product['default_price']) && is_array($product['default_price']) ? $product['default_price'] : [];
                $amount = isset($default_price['unit_amount']) ? (int) $default_price['unit_amount'] : 0;
                $currency = strtoupper((string) ($default_price['currency'] ?? 'GBP'));
            ?>
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:700; color:#0f172a;"><?php echo esc_html((string) ($product['name'] ?? 'Unnamed product')); ?></div>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                <?php echo esc_html($currency); ?> <?php echo esc_html(number_format(($amount / 100), 2)); ?>
                                <?php if ($destination_account !== ''): ?>
                                    · Destination: <?php echo esc_html($destination_account); ?>
                                <?php else: ?>
                                    · No connected account mapping
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="action" value="gb_stripe_storefront_checkout">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                            <?php wp_nonce_field('gb_stripe_storefront_checkout'); ?>
                            <input type="number" name="quantity" min="1" value="1" style="width:70px; border:1px solid #e2e8f0; border-radius:6px; padding:8px;">
                            <button type="submit" <?php disabled($destination_account === ''); ?> style="border:1px solid #2e7d32; background:#2e7d32; color:#fff; border-radius:6px; padding:9px 14px; font-weight:700; cursor:pointer;">Buy</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:18px; border-top:1px solid #f1f5f9; padding-top:14px;">
            <h4 style="margin:0 0 8px; font-size:13px; color:#0f172a;">Connected Accounts</h4>
            <?php if (empty($cleaner_accounts)): ?>
                <div style="font-size:12px; color:#64748b;">No connected cleaner accounts found.</div>
            <?php else: ?>
                <ul style="margin:0; padding-left:18px; color:#334155; font-size:12px;">
                    <?php foreach ($cleaner_accounts as $item): ?>
                        <li><?php echo esc_html($item['name']); ?> · <?php echo esc_html($item['account_id']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}
add_shortcode('gb_stripe_storefront', 'gb_render_stripe_storefront_shortcode');

function gb_extract_connected_account_id_from_event($event) {
    if (!is_array($event)) {
        return '';
    }

    $candidates = [];
    $paths = [
        ['account'],
        ['context', 'account'],
        ['data', 'object', 'id'],
        ['data', 'related_object', 'id'],
        ['related_object', 'id'],
        ['related_object'],
    ];

    foreach ($paths as $path) {
        $value = $event;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = null;
                break;
            }
            $value = $value[$segment];
        }
        if (is_string($value) && $value !== '') {
            $candidates[] = $value;
        }
    }

    foreach ($candidates as $candidate) {
        $candidate = sanitize_text_field($candidate);
        if (strpos($candidate, 'acct_') === 0) {
            return $candidate;
        }
    }

    return '';
}

function gb_handle_stripe_webhook() {
    $config = gb_get_stripe_config();
    $payload = file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

    $secrets = array_filter([
        isset($config['webhook_secret']) ? $config['webhook_secret'] : '',
        isset($config['connect_webhook_secret']) ? $config['connect_webhook_secret'] : '',
    ], function ($secret) {
        return !gb_stripe_value_is_placeholder($secret);
    });
    if (empty($secrets)) {
        status_header(400);
        echo 'Stripe webhook secret not configured.';
        exit;
    }

    $valid_signature = false;
    foreach ($secrets as $secret) {
        if (gb_stripe_verify_signature($payload, $signature, $secret)) {
            $valid_signature = true;
            break;
        }
    }

    if (!$valid_signature) {
        status_header(400);
        echo 'Invalid Stripe signature.';
        exit;
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        status_header(400);
        echo 'Invalid Stripe event payload.';
        exit;
    }

    $event_type = sanitize_text_field((string) $event['type']);
    $event_object = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : null;

    // Connect v2 thin events: fetch and sync account status so dashboard stays current.
    if (
        $event_type === 'v2.core.account[requirements].updated' ||
        strpos($event_type, 'v2.core.account[') === 0
    ) {
        $account_id = gb_extract_connected_account_id_from_event($event);

        if ($account_id === '' && !empty($event['id'])) {
            $full_event = gb_stripe_api_request_v2('core/events/' . rawurlencode($event['id']), [], 'GET');
            if (!is_wp_error($full_event) && is_array($full_event)) {
                $account_id = gb_extract_connected_account_id_from_event($full_event);
            }
        }

        if ($account_id !== '') {
            $cleaner_id = gb_find_cleaner_by_account_id($account_id);
            if ($cleaner_id) {
                gb_sync_cleaner_connect_account($cleaner_id);
            }
        }

        status_header(200);
        echo 'ok';
        exit;
    }

    if (!is_array($event_object)) {
        status_header(400);
        echo 'Invalid Stripe event payload.';
        exit;
    }

    $syncable_events = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    if (in_array($event_type, $syncable_events, true)) {
        $booking_id = isset($event_object['metadata']['booking_id']) ? absint($event_object['metadata']['booking_id']) : 0;
        $session_id = isset($event_object['id']) ? sanitize_text_field($event_object['id']) : '';

        if ($booking_id && $session_id) {
            $synced = gb_sync_booking_from_checkout_session($booking_id, $session_id);
            if (is_wp_error($synced)) {
                status_header(400);
                echo 'Stripe sync error.';
                exit;
            }
        }
    }

    if ($event_type === 'account.updated' && !empty($event_object['id'])) {
        $connected_account_id = sanitize_text_field((string) $event_object['id']);
        $cleaner_id = gb_find_cleaner_by_account_id($connected_account_id);
        if ($cleaner_id) {
            gb_sync_cleaner_connect_meta_from_account($cleaner_id, $event_object);
        }
    }

    // Memberships. Everything above this line is bookings and Connect; the
    // recurring side lives in inc/membership-checkout.php and picks out the
    // events it cares about for itself.
    if (function_exists('gb_membership_handle_stripe_event')) {
        gb_membership_handle_stripe_event($event_type, $event_object);
    }

    status_header(200);
    echo 'ok';
    exit;
}
add_action('admin_post_gb_stripe_webhook', 'gb_handle_stripe_webhook');
add_action('admin_post_nopriv_gb_stripe_webhook', 'gb_handle_stripe_webhook');

/**
 * Initialize Stripe PHP SDK (Optional Helper)
 * Call this before making any direct API calls.
 */
function gb_init_stripe() {
    $config = gb_get_stripe_config();
    if ( ! empty($config['secret_key']) ) {
        // Require the Stripe library if you are using Composer or manual loading
        // \Stripe\Stripe::setApiKey($config['secret_key']);
    }
}
