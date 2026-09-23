<?php
/* Template Name: Booking Engine */

// 1. FETCH SERVICES FROM DB
$services = [];
$service_query = new WP_Query([
    'post_type'      => 'gb_service',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

if ($service_query->have_posts()) {
    while ($service_query->have_posts()) {
        $service_query->the_post();
        $s_id = get_the_ID();
        $cost = get_post_meta($s_id, 'service_price', true) ?: 0;
        $default_duration = (int) get_post_meta($s_id, '_gb_service_duration', true);
        if ($default_duration < 1 || $default_duration > 8) {
            $default_duration = 3;
        }

        $services[$s_id] = [
            'label'            => get_the_title(),
            'extra_cost'       => (float) $cost,
            'default_duration' => $default_duration,
        ];
    }
    wp_reset_postdata();
} else {
    $services[0] = [
        'label'            => 'Regular Clean',
        'extra_cost'       => 0.0,
        'default_duration' => 3,
    ];
}

$default_service_id = (int) array_key_first($services);
$time_slots = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
$duration_options = range(1, 8);

// 2. VIEW DATA & FORM STATE
$current_user = wp_get_current_user();
$cleaner_id = isset($_GET['cleaner']) ? intval($_GET['cleaner']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleaner_id'])) {
    $cleaner_id = intval($_POST['cleaner_id']);
}

if ($cleaner_id && get_post_type($cleaner_id) !== 'gb_cleaner') {
    $cleaner_id = 0;
}

$cleaner_name = $cleaner_id ? get_the_title($cleaner_id) : 'Any Professional';
$base_rate = $cleaner_id ? gb_get_cleaner_rate($cleaner_id) : 15;
$today_ymd = current_time('Y-m-d');

$form_data = [
    'service_id' => $default_service_id,
    'duration'   => 3,
    'date'       => '',
    'time'       => '10:00',
    'fullname'   => $current_user->ID ? ($current_user->first_name ?: $current_user->display_name) : '',
    'phone'      => '',
    'email'      => $current_user->ID ? $current_user->user_email : '',
    'address'    => '',
];

$error_messages = [];
$info_message = '';
$destination_account_id = '';
$platform_fee_percent = 0.0;
$platform_fee_pence = 0;
if (isset($_GET['payment']) && $_GET['payment'] === 'cancelled') {
    $info_message = 'Payment was cancelled. Your booking was not confirmed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_action']) && $_POST['gb_action'] === 'make_booking') {
    $form_data['service_id'] = isset($_POST['service_id']) ? intval($_POST['service_id']) : $default_service_id;
    $form_data['duration'] = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
    $form_data['date'] = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
    $form_data['time'] = isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '';
    $form_data['fullname'] = isset($_POST['fullname']) ? sanitize_text_field(wp_unslash($_POST['fullname'])) : '';
    $form_data['phone'] = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $form_data['email'] = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $form_data['address'] = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';

    if (empty($_POST['gb_booking_nonce']) || !wp_verify_nonce($_POST['gb_booking_nonce'], 'gb_make_booking')) {
        $error_messages[] = 'Your session expired. Please refresh the page and try again.';
    }

    if (empty($error_messages)) {
        $rate_state = gb_rate_limit_check('booking_form', 8, HOUR_IN_SECONDS, $form_data['email']);
        if ($rate_state['blocked']) {
            $error_messages[] = gb_get_rate_limit_message($rate_state);
        } else {
            gb_record_rate_limit_hit('booking_form', HOUR_IN_SECONDS, $form_data['email']);
        }
    }

    if ($cleaner_id && get_post_type($cleaner_id) !== 'gb_cleaner') {
        $error_messages[] = 'Invalid cleaner selected.';
    }

    if (!isset($services[$form_data['service_id']])) {
        $form_data['service_id'] = $default_service_id;
        $error_messages[] = 'Please choose a valid service.';
    }

    if (!in_array($form_data['time'], $time_slots, true)) {
        $error_messages[] = 'Please select a valid start time.';
    }

    if ($form_data['duration'] < 1 || $form_data['duration'] > 8) {
        $error_messages[] = 'Booking duration must be between 1 and 8 hours.';
    }

    if (empty($form_data['fullname']) || strlen($form_data['fullname']) < 2) {
        $error_messages[] = 'Please enter your full name.';
    }

    if (empty($form_data['address']) || strlen($form_data['address']) < 8) {
        $error_messages[] = 'Please enter a full property address.';
    }

    if (!is_email($form_data['email'])) {
        $error_messages[] = 'Please enter a valid email address.';
    }

    if (!preg_match('/^[0-9+()\\-\\s]{7,25}$/', $form_data['phone'])) {
        $error_messages[] = 'Please enter a valid phone number.';
    }

    $booking_dt = DateTime::createFromFormat('Y-m-d H:i', $form_data['date'] . ' ' . $form_data['time']);
    $valid_datetime = $booking_dt && $booking_dt->format('Y-m-d H:i') === ($form_data['date'] . ' ' . $form_data['time']);
    if (!$valid_datetime) {
        $error_messages[] = 'Please choose a valid booking date and time.';
    }

    if ($valid_datetime) {
        $booking_ts = strtotime($form_data['date'] . ' ' . $form_data['time']);
        if ($booking_ts < current_time('timestamp')) {
            $error_messages[] = 'Please choose a future date and time.';
        }
    }

    if (empty($error_messages) && $cleaner_id > 0) {
        $new_start = strtotime($form_data['date'] . ' ' . $form_data['time']);
        $new_end = $new_start + ($form_data['duration'] * 3600);

        $existing = new WP_Query([
            'post_type'      => 'gb_booking',
            'post_status'    => ['publish'],
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_gb_booking_cleaner_id', 'value' => $cleaner_id],
                ['key' => '_gb_booking_date', 'value' => $form_data['date']],
            ],
        ]);

        if ($existing->have_posts()) {
            while ($existing->have_posts()) {
                $existing->the_post();
                $ex_time = get_post_meta(get_the_ID(), '_gb_booking_time', true);
                $ex_duration = (int) get_post_meta(get_the_ID(), '_gb_booking_duration', true);
                $ex_start = strtotime($form_data['date'] . ' ' . $ex_time);
                $ex_end = $ex_start + ($ex_duration * 3600);

                if ($new_start < $ex_end && $new_end > $ex_start) {
                    $error_messages[] = 'That cleaner is already booked for this slot. Please choose another time.';
                    break;
                }
            }
            wp_reset_postdata();
        }
    }

    if (empty($error_messages)) {
        $service_label = $services[$form_data['service_id']]['label'];
        $service_extra = (float) $services[$form_data['service_id']]['extra_cost'];
        $final_hourly_rate = $base_rate + $service_extra;
        $total_amount_gbp = round($final_hourly_rate * $form_data['duration'], 2);
        $total_amount_pence = (int) round($total_amount_gbp * 100);
        if ($total_amount_pence < 50) {
            $total_amount_pence = 50;
            $total_amount_gbp = 0.50;
        }

        $stripe_config = gb_get_stripe_config();
        $stripe_missing = function_exists('gb_get_stripe_config_issues')
            ? gb_get_stripe_config_issues($stripe_config, ['secret_key', 'publishable_key'])
            : [];
        if (!empty($stripe_missing)) {
            $error_messages[] = 'Stripe is not configured yet. Please contact support before booking.';
        } else {
            $platform_fee_percent = (float) ($stripe_config['platform_fee'] ?? 0);
            if ($platform_fee_percent < 0) {
                $platform_fee_percent = 0;
            }
            if ($platform_fee_percent > 100) {
                $platform_fee_percent = 100;
            }
            $platform_fee_pence = (int) round($total_amount_pence * ($platform_fee_percent / 100));

            if ($cleaner_id > 0) {
                $destination_account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
                $charges_enabled = get_post_meta($cleaner_id, '_gb_stripe_charges_enabled', true) === '1';
                if ($destination_account_id === '') {
                    $error_messages[] = 'This cleaner is not connected to Stripe yet. Please choose another cleaner or ask support.';
                } elseif (!$charges_enabled) {
                    $error_messages[] = 'This cleaner has not completed Stripe onboarding. Please choose another cleaner or try again shortly.';
                }
            }
        }
    }

    if (empty($error_messages)) {
        $service_label = $services[$form_data['service_id']]['label'];
        $user_id = get_current_user_id();
        $booking_title = sprintf('%s - %s (%s)', $service_label, $form_data['fullname'], $form_data['date']);

        $booking_id = wp_insert_post([
            'post_type'   => 'gb_booking',
            'post_title'  => $booking_title,
            'post_status' => 'pending',
            'post_author' => $user_id,
        ]);

        if ($booking_id) {
            update_post_meta($booking_id, '_gb_booking_cleaner_id', $cleaner_id);
            update_post_meta($booking_id, '_gb_booking_service', $service_label);
            update_post_meta($booking_id, '_gb_booking_date', $form_data['date']);
            update_post_meta($booking_id, '_gb_booking_time', $form_data['time']);
            update_post_meta($booking_id, '_gb_booking_duration', $form_data['duration']);
            update_post_meta($booking_id, '_gb_customer_name', $form_data['fullname']);
            update_post_meta($booking_id, '_gb_customer_email', $form_data['email']);
            update_post_meta($booking_id, '_gb_customer_phone', $form_data['phone']);
            update_post_meta($booking_id, '_gb_customer_address', $form_data['address']);
            update_post_meta($booking_id, '_gb_booking_status', 'pending_payment');
            update_post_meta($booking_id, '_gb_payment_provider', 'stripe');
            update_post_meta($booking_id, '_gb_payment_status', 'pending');
            update_post_meta($booking_id, '_gb_booking_total_gbp', number_format($total_amount_gbp, 2, '.', ''));
            update_post_meta($booking_id, '_gb_platform_fee_percent', number_format($platform_fee_percent, 2, '.', ''));
            update_post_meta($booking_id, '_gb_platform_fee_gbp', number_format($platform_fee_pence / 100, 2, '.', ''));
            update_post_meta($booking_id, '_gb_cleaner_payout_estimate_gbp', number_format(max(0, $total_amount_gbp - ($platform_fee_pence / 100)), 2, '.', ''));
            if ($destination_account_id !== '') {
                update_post_meta($booking_id, '_gb_stripe_destination_account', $destination_account_id);
            }

            $access_token = gb_generate_booking_access_token($booking_id);
            $success_url = add_query_arg([
                'bid'        => $booking_id,
                't'          => $access_token,
                'checkout'   => 'success',
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ], home_url('/booking-success'));
            $cancel_url = add_query_arg([
                'cleaner' => $cleaner_id,
                'payment' => 'cancelled',
            ], home_url('/book'));

            $session_args = [
                'mode'            => 'payment',
                'success_url'     => $success_url,
                'cancel_url'      => $cancel_url,
                'customer_email'  => $form_data['email'],
                'client_reference_id' => (string) $booking_id,
                'metadata'        => [
                    'booking_id' => (string) $booking_id,
                    'cleaner_id' => (string) $cleaner_id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'booking_id' => (string) $booking_id,
                        'cleaner_id' => (string) $cleaner_id,
                    ],
                ],
                'line_items'      => [
                    [
                        'quantity'   => 1,
                        'price_data' => [
                            'currency'     => 'gbp',
                            'unit_amount'  => $total_amount_pence,
                            'product_data' => [
                                'name'        => $service_label . ' Booking',
                                'description' => sprintf(
                                    '%s on %s at %s (%d hour%s)',
                                    $cleaner_name,
                                    $form_data['date'],
                                    $form_data['time'],
                                    $form_data['duration'],
                                    $form_data['duration'] === 1 ? '' : 's'
                                ),
                            ],
                        ],
                    ],
                ],
            ];

            if ($destination_account_id !== '') {
                $session_args['payment_intent_data']['application_fee_amount'] = max(0, $platform_fee_pence);
                $session_args['payment_intent_data']['transfer_data'] = [
                    'destination' => $destination_account_id,
                ];
                $session_args['payment_intent_data']['on_behalf_of'] = $destination_account_id;
            }

            $checkout_session = gb_stripe_api_request('checkout/sessions', $session_args, 'POST');
            if (is_wp_error($checkout_session)) {
                wp_delete_post($booking_id, true);
                $error_messages[] = 'Payment setup failed: ' . $checkout_session->get_error_message();
            } else {
                update_post_meta($booking_id, '_gb_stripe_checkout_session_id', sanitize_text_field($checkout_session['id']));
                if (!empty($checkout_session['url'])) {
                    wp_redirect(esc_url_raw($checkout_session['url']));
                    exit;
                }
                wp_delete_post($booking_id, true);
                $error_messages[] = 'Payment setup failed: no checkout URL returned.';
            }
        } else {
            $error_messages[] = 'System error: Could not create booking. Please try again.';
        }
    }
}

get_header();
?>

<style>
    #gb-book-page { background: #f8fafc; min-height: 100vh; padding: 40px 20px; font-family: 'Helvetica Neue', sans-serif; }
    .gb-book-container { max-width: 980px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr; gap: 32px; }
    .gb-form-card { background: white; padding: 36px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .gb-summary-card { background: #0f172a; color: #f8fafc; padding: 28px; border-radius: 12px; height: fit-content; position: sticky; top: 20px; }
    h1 { margin-top: 0; color: #0f172a; font-size: 24px; }
    .gb-step-title { font-size: 13px; text-transform: uppercase; color: #64748b; font-weight: 800; margin-bottom: 15px; display: block; letter-spacing: 0.04em; }
    .gb-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    label { display: block; margin-bottom: 8px; font-weight: 700; color: #334155; font-size: 14px; }
    input, select { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 15px; }
    input:focus, select:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16,185,129,0.12); }
    .gb-total-row { display: flex; justify-content: space-between; font-size: 19px; font-weight: 800; margin-top: 18px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.2); }
    .gb-btn-submit { width: 100%; background: #047857; color: white; border: none; padding: 16px; border-radius: 8px; font-weight: 800; font-size: 15px; cursor: pointer; margin-top: 18px; transition: 0.2s; }
    .gb-btn-submit:hover { background: #065f46; }
    .gb-btn-submit[disabled] { opacity: 0.8; cursor: not-allowed; }
    .gb-login-prompt { background: #e0f2fe; color: #0369a1; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; }
    .gb-field-help { margin-top: 6px; font-size: 12px; color: #64748b; }
    .gb-summary-card p { color: #f8fafc; }
    .gb-summary-detail { margin-bottom: 6px; font-size: 14px; color: #e2e8f0; }
    .gb-summary-meta { font-size: 13px; color: #e2e8f0; margin: 8px 0 0; }
    .gb-summary-note { color: #cbd5e1; font-size: 12px; text-align: center; margin-top: 15px; }
    .gb-error-box { background:#fee2e2; color:#991b1b; max-width:980px; margin:0 auto 20px; border-radius:10px; border:1px solid #fecaca; padding:14px 16px; }
    .gb-error-box ul { margin: 8px 0 0 18px; padding: 0; }
    .gb-error-box li { margin-bottom: 4px; }
    @media (max-width: 900px) { .gb-book-container { grid-template-columns: 1fr; } }
</style>

<main id="main-content">
<div id="gb-book-page">
    <?php if (!empty($info_message)) : ?>
        <div role="status" style="background:#e0f2fe; color:#075985; max-width:980px; margin:0 auto 20px; border-radius:10px; border:1px solid #bae6fd; padding:14px 16px;">
            <?php echo esc_html($info_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_messages)) : ?>
        <div class="gb-error-box" role="alert" aria-live="polite">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($error_messages as $msg) : ?>
                    <li><?php echo esc_html($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="gb-book-container" id="gb-booking-form" novalidate>
        <input type="hidden" name="gb_action" value="make_booking">
        <input type="hidden" name="cleaner_id" value="<?php echo esc_attr($cleaner_id); ?>">
        <?php wp_nonce_field('gb_make_booking', 'gb_booking_nonce'); ?>

        <div class="gb-form-card">
<?php
/*
 * The booking page's wording was the last customer-facing copy still fixed in
 * the template. Defaults are exactly what was here before, so nothing moves
 * until somebody edits it in the dashboard.
 */
$bk_title   = gb_page_text( '_gb_bk_title', 'Complete your Booking', 'book-now' );
$bk_l_svc   = gb_page_text( '_gb_bk_label_service', 'Service Type', 'book-now' );
$bk_l_dur   = gb_page_text( '_gb_bk_label_duration', 'Duration', 'book-now' );
$bk_l_date  = gb_page_text( '_gb_bk_label_date', 'Date', 'book-now' );
$bk_l_time  = gb_page_text( '_gb_bk_label_time', 'Start Time', 'book-now' );
$bk_help    = gb_page_text( '_gb_bk_time_help', 'Choose a start time that suits you.', 'book-now' );
$bk_l_name  = gb_page_text( '_gb_bk_label_name', 'Full Name', 'book-now' );
$bk_l_phone = gb_page_text( '_gb_bk_label_phone', 'Phone Number', 'book-now' );
$bk_l_email = gb_page_text( '_gb_bk_label_email', 'Email Address', 'book-now' );
$bk_l_addr  = gb_page_text( '_gb_bk_label_address', 'Property Address', 'book-now' );
$bk_submit  = gb_page_text( '_gb_bk_submit', 'Continue to Secure Payment', 'book-now' );
$bk_note    = gb_page_text( '_gb_bk_note', 'You will be redirected to Stripe to pay securely and confirm your booking.', 'book-now' );
?>
            <h1><?php echo esc_html($bk_title); ?></h1>
            <?php if (!is_user_logged_in()) : ?>
                <div class="gb-login-prompt">Already have an account? <a href="<?php echo esc_url(home_url('/login')); ?>" style="font-weight:bold; color:inherit;">Log in</a> to pre-fill your details.</div>
            <?php endif; ?>

            <span class="gb-step-title">1. Service Details</span>
            <div class="gb-field-row">
                <div>
                    <label for="service-select"><?php echo esc_html($bk_l_svc); ?></label>
                    <select name="service_id" id="service-select">
                        <?php foreach ($services as $id => $service) : ?>
                            <option
                                value="<?php echo esc_attr($id); ?>"
                                data-extra="<?php echo esc_attr($service['extra_cost']); ?>"
                                data-default-duration="<?php echo esc_attr($service['default_duration']); ?>"
                                data-label="<?php echo esc_attr($service['label']); ?>"
                                <?php selected($form_data['service_id'], $id); ?>
                            >
                                <?php echo esc_html($service['label']); ?>
                                <?php if ($service['extra_cost'] > 0) : ?>
                                    <?php echo esc_html(sprintf(' (+£%s/hr)', $service['extra_cost'])); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="duration-select"><?php echo esc_html($bk_l_dur); ?></label>
                    <select name="duration" id="duration-select">
                        <?php foreach ($duration_options as $duration_option) : ?>
                            <option value="<?php echo esc_attr($duration_option); ?>" <?php selected($form_data['duration'], $duration_option); ?>>
                                <?php echo esc_html($duration_option . ' Hour' . ($duration_option > 1 ? 's' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="gb-field-row">
                <div>
                    <label for="booking-date"><?php echo esc_html($bk_l_date); ?></label>
                    <input type="date" id="booking-date" name="date" required min="<?php echo esc_attr($today_ymd); ?>" value="<?php echo esc_attr($form_data['date']); ?>">
                </div>
                <div>
                    <label for="booking-time"><?php echo esc_html($bk_l_time); ?></label>
                    <select id="booking-time" name="time">
                        <?php foreach ($time_slots as $slot) : ?>
                            <option value="<?php echo esc_attr($slot); ?>" <?php selected($form_data['time'], $slot); ?>>
                                <?php echo esc_html($slot); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="gb-field-help" id="time-slot-help"><?php echo esc_html($bk_help); ?></div>
                </div>
            </div>

            <hr style="margin: 28px 0; border:0; border-top:1px solid #eee;">

            <span class="gb-step-title">2. Your Details</span>
            <div class="gb-field-row">
                <div>
                    <label for="booking-fullname"><?php echo esc_html($bk_l_name); ?></label>
                    <input id="booking-fullname" type="text" name="fullname" required value="<?php echo esc_attr($form_data['fullname']); ?>">
                </div>
                <div>
                    <label for="booking-phone"><?php echo esc_html($bk_l_phone); ?></label>
                    <input id="booking-phone" type="tel" name="phone" required value="<?php echo esc_attr($form_data['phone']); ?>" placeholder="+44 7...">
                </div>
            </div>
            <div class="gb-field-row">
                <div style="grid-column: span 2;">
                    <label for="booking-email"><?php echo esc_html($bk_l_email); ?></label>
                    <input id="booking-email" type="email" name="email" required value="<?php echo esc_attr($form_data['email']); ?>">
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label for="booking-address"><?php echo esc_html($bk_l_addr); ?></label>
                <input id="booking-address" type="text" name="address" required placeholder="Street, City, Postcode" value="<?php echo esc_attr($form_data['address']); ?>">
            </div>
        </div>

        <aside class="gb-summary-card" aria-labelledby="booking-summary-heading">
            <h2 id="booking-summary-heading" style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:15px; margin-bottom:15px;">Booking Summary</h2>
            <p style="margin-bottom:10px;"><strong>Professional:</strong><br><?php echo esc_html($cleaner_name); ?></p>
            <p style="margin-bottom:10px;"><strong>Service:</strong><br><span id="summary-service">-</span></p>
            <p style="margin-bottom:10px;"><strong>Base Rate:</strong><br>£<?php echo esc_html($base_rate); ?> / hour</p>
            <p class="gb-summary-detail"><strong>Service Surcharge:</strong> <span id="surcharge-display">£0</span> / hr</p>
            <p class="gb-summary-meta"><strong>Slot:</strong> <span id="summary-slot">Choose date and time</span></p>
            <p class="gb-summary-meta"><strong>Estimated Finish:</strong> <span id="summary-end">-</span></p>

            <div class="gb-total-row"><span>Total Est.</span><span>£<span id="total-display">0</span></span></div>
            <button type="submit" class="gb-btn-submit" id="gb-submit-btn"><?php echo esc_html($bk_submit); ?></button>
            <p class="gb-summary-note"><?php echo esc_html($bk_note); ?></p>
        </aside>
    </form>
</div>
</main>

<script>
(function () {
    var form = document.getElementById('gb-booking-form');
    var serviceSelect = document.getElementById('service-select');
    var durationSelect = document.getElementById('duration-select');
    var dateInput = document.getElementById('booking-date');
    var timeSelect = document.getElementById('booking-time');
    var submitBtn = document.getElementById('gb-submit-btn');
    var timeSlotHelp = document.getElementById('time-slot-help');
    var serviceSummary = document.getElementById('summary-service');
    var slotSummary = document.getElementById('summary-slot');
    var endSummary = document.getElementById('summary-end');
    var surchargeDisplay = document.getElementById('surcharge-display');
    var totalDisplay = document.getElementById('total-display');

    var baseRate = <?php echo wp_json_encode((float) $base_rate); ?>;
    var userChangedDuration = false;

    function minutesFromTime(timeStr) {
        var parts = String(timeStr || '').split(':');
        if (parts.length !== 2) return null;
        var hours = parseInt(parts[0], 10);
        var mins = parseInt(parts[1], 10);
        if (isNaN(hours) || isNaN(mins)) return null;
        return (hours * 60) + mins;
    }

    function format12Hour(timeStr) {
        var mins = minutesFromTime(timeStr);
        if (mins === null) return '-';
        var hours24 = Math.floor(mins / 60);
        var minutes = mins % 60;
        var suffix = hours24 >= 12 ? 'PM' : 'AM';
        var hours12 = hours24 % 12;
        if (hours12 === 0) hours12 = 12;
        return hours12 + ':' + String(minutes).padStart(2, '0') + ' ' + suffix;
    }

    function addHours(timeStr, hoursToAdd) {
        var mins = minutesFromTime(timeStr);
        if (mins === null) return null;
        var outputMins = mins + (hoursToAdd * 60);
        var wrapped = outputMins % (24 * 60);
        if (wrapped < 0) wrapped += (24 * 60);
        var h = String(Math.floor(wrapped / 60)).padStart(2, '0');
        var m = String(wrapped % 60).padStart(2, '0');
        return h + ':' + m;
    }

    function enforceFutureTimeOptions() {
        var selectedDate = dateInput.value;
        if (!selectedDate) {
            timeSlotHelp.textContent = 'Choose a start time that suits you.';
            return;
        }

        var now = new Date();
        var todayIso = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        var cutoffMinutes = (now.getHours() * 60) + now.getMinutes() + 30;
        var hasAvailableOption = false;

        Array.prototype.forEach.call(timeSelect.options, function (opt) {
            var optionMins = minutesFromTime(opt.value);
            var disable = selectedDate === todayIso && optionMins !== null && optionMins < cutoffMinutes;
            opt.disabled = !!disable;
            if (!disable) hasAvailableOption = true;
        });

        if (timeSelect.selectedOptions[0] && timeSelect.selectedOptions[0].disabled) {
            var fallback = null;
            Array.prototype.forEach.call(timeSelect.options, function (opt) {
                if (!fallback && !opt.disabled) fallback = opt;
            });
            if (fallback) timeSelect.value = fallback.value;
        }

        if (!hasAvailableOption) {
            timeSlotHelp.textContent = 'No times left for today. Please pick another date.';
        } else if (selectedDate === todayIso) {
            timeSlotHelp.textContent = 'Earlier times are hidden because they are too close to now.';
        } else {
            timeSlotHelp.textContent = 'Choose a start time that suits you.';
        }
    }

    function updateTotal() {
        var selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        var extraCost = parseFloat(selectedOption.getAttribute('data-extra')) || 0;
        var duration = parseInt(durationSelect.value, 10) || 0;
        var finalRate = baseRate + extraCost;
        var total = finalRate * duration;

        var serviceLabel = selectedOption.getAttribute('data-label') || selectedOption.textContent.trim();
        serviceSummary.textContent = serviceLabel;
        surchargeDisplay.textContent = '£' + extraCost.toFixed(2);
        totalDisplay.textContent = total.toFixed(2);

        if (dateInput.value && timeSelect.value) {
            slotSummary.textContent = dateInput.value + ' at ' + format12Hour(timeSelect.value);
            var endTime = addHours(timeSelect.value, duration);
            endSummary.textContent = endTime ? format12Hour(endTime) : '-';
        } else {
            slotSummary.textContent = 'Choose date and time';
            endSummary.textContent = '-';
        }
    }

    serviceSelect.addEventListener('change', function () {
        if (!userChangedDuration) {
            var suggested = parseInt(serviceSelect.options[serviceSelect.selectedIndex].getAttribute('data-default-duration') || '', 10);
            if (!isNaN(suggested) && durationSelect.querySelector('option[value="' + suggested + '"]')) {
                durationSelect.value = String(suggested);
            }
        }
        updateTotal();
    });

    durationSelect.addEventListener('change', function () {
        userChangedDuration = true;
        updateTotal();
    });

    dateInput.addEventListener('change', function () {
        enforceFutureTimeOptions();
        updateTotal();
    });

    timeSelect.addEventListener('change', updateTotal);

    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Confirming...';
    });

    enforceFutureTimeOptions();
    updateTotal();
})();
</script>

<?php get_footer(); ?>
