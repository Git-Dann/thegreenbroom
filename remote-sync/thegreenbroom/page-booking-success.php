<?php
/* Template Name: Booking Success */

$booking_id = isset($_GET['bid']) ? intval($_GET['bid']) : 0;
$access_token = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';
$valid = false;
$post = null;
$msg = '';
$can_create_guest_account = false;
$payment_notice = '';
$payment_error = '';
$is_paid = true;

// 2. FETCH BOOKING DATA
if ($booking_id) {
    $post = get_post($booking_id);
    if ($post && $post->post_type == 'gb_booking') {
        $current_user = wp_get_current_user();
        $is_manager = current_user_can('administrator') || in_array('gb_manager', (array) $current_user->roles, true);
        $is_owner = is_user_logged_in() && ((int) $post->post_author === get_current_user_id());
        $has_valid_token = gb_booking_token_is_valid($booking_id, $access_token);
        $can_view_booking = $is_manager || $is_owner || $has_valid_token;

        if ($can_view_booking) {
            $valid = true;
        
            $name = get_post_meta($booking_id, '_gb_customer_name', true);
            $date = get_post_meta($booking_id, '_gb_booking_date', true);
            $time = get_post_meta($booking_id, '_gb_booking_time', true);
            $service = get_post_meta($booking_id, '_gb_booking_service', true);
            $cleaner_id = get_post_meta($booking_id, '_gb_booking_cleaner_id', true);
            $cleaner_name = get_the_title($cleaner_id);
            $address = get_post_meta($booking_id, '_gb_customer_address', true);
            
            // Time Calcs
            $duration = (int) get_post_meta($booking_id, '_gb_booking_duration', true);
            $start_ts = strtotime("$date $time");
            $end_ts = $start_ts + ($duration * 3600);
            $end_time_display = date('g:i A', $end_ts);
            $checkout_status = isset($_GET['checkout']) ? sanitize_key($_GET['checkout']) : '';
            $checkout_session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';

            if ($checkout_status === 'success' && $checkout_session_id && ($has_valid_token || $is_owner || $is_manager)) {
                $sync_result = gb_sync_booking_from_checkout_session($booking_id, $checkout_session_id);
                if (is_wp_error($sync_result)) {
                    $payment_error = 'We could not verify your Stripe payment yet. Please refresh in 10 seconds.';
                } else {
                    $payment_notice = 'Stripe payment received successfully.';
                    $post = get_post($booking_id);
                }
            }

            $payment_status = get_post_meta($booking_id, '_gb_payment_status', true);
            $uses_stripe = get_post_meta($booking_id, '_gb_payment_provider', true) === 'stripe';
            $is_paid = !$uses_stripe || $payment_status === 'paid';

            if ($uses_stripe && !$is_paid && empty($payment_error) && empty($payment_notice)) {
                $payment_notice = 'Payment is still processing. Your slot is held pending confirmation.';
            }

            $can_create_guest_account = ((int) $post->post_author === 0) && $has_valid_token && $is_paid;

            // 3. HANDLE ACCOUNT CREATION POST REQUEST
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_create_account']) && $can_create_guest_account) {
                if (empty($_POST['gb_create_account_nonce']) || !wp_verify_nonce($_POST['gb_create_account_nonce'], 'gb_create_account_' . $booking_id)) {
                    $msg = '<div style="color:#b91c1c; margin-bottom:10px;">Security check failed. Please refresh and try again.</div>';
                } else {
                    $password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
                    $email = get_post_meta($booking_id, '_gb_customer_email', true);
                    $name = get_post_meta($booking_id, '_gb_customer_name', true);
                    $rate_state = gb_rate_limit_check('guest_account_create', 5, HOUR_IN_SECONDS, $email);
                    $password_check = gb_validate_account_password($password);

                    if ($rate_state['blocked']) {
                        $msg = '<div style="color:#b91c1c; margin-bottom:10px;">' . esc_html(gb_get_rate_limit_message($rate_state)) . '</div>';
                    } elseif (is_wp_error($password_check)) {
                        $msg = '<div style="color:#b91c1c; margin-bottom:10px;">' . esc_html($password_check->get_error_message()) . '</div>';
                    } elseif (!email_exists($email)) {
                        gb_record_rate_limit_hit('guest_account_create', HOUR_IN_SECONDS, $email);
                        // Create User
                        $user_id = wp_create_user($email, $password, $email);
                        if (!is_wp_error($user_id)) {
                            $user = new WP_User($user_id);
                            $user->set_role('gb_customer');
                            wp_update_user(['ID' => $user_id, 'first_name' => $name, 'display_name' => $name]);

                            // Link Booking to New User
                            wp_update_post(['ID' => $booking_id, 'post_author' => $user_id]);

                            // Auto Login
                            wp_set_current_user($user_id);
                            wp_set_auth_cookie($user_id);
                            
                            // Redirect to Dashboard
                            gb_reset_rate_limit('guest_account_create', $email);
                            $verification_state = gb_prepare_new_user_email_verification($user_id, home_url('/my-account/'));
                            wp_safe_redirect($verification_state['redirect']);
                            exit;
                        }

                        $msg = '<div style="color:#b91c1c; margin-bottom:10px;">We could not create your account. Please try again.</div>';
                    } else {
                        gb_record_rate_limit_hit('guest_account_create', HOUR_IN_SECONDS, $email);
                        $msg = '<div style="color:#b91c1c; margin-bottom:10px;">An account with this email already exists.</div>';
                    }
                }
            }
            
            // ICAL LOGIC
            if (isset($_GET['download_ics'])) {
                $filename = 'booking_' . absint($booking_id) . '.ics';
                $ics_start = gmdate('Ymd\THis\Z', $start_ts);
                $ics_end   = gmdate('Ymd\THis\Z', $end_ts);
                $now       = gmdate('Ymd\THis\Z');
                $summary = preg_replace('/[\r\n]+/', ' ', 'Cleaning: ' . $service . ' with ' . $cleaner_name);
                $location = preg_replace('/[\r\n]+/', ' ', (string) $address);
                
                $ics_content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//The Green Broom//EN\r\nBEGIN:VEVENT\r\nUID:gb-$booking_id\r\nDTSTAMP:$now\r\nDTSTART:$ics_start\r\nDTEND:$ics_end\r\nSUMMARY:$summary\r\nLOCATION:$location\r\nEND:VEVENT\r\nEND:VCALENDAR";

                header('Content-Type: text/calendar; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filename);
                echo $ics_content;
                exit;
            }
            
            // Google Cal Link
            $g_start = gmdate('Ymd\THis\Z', $start_ts);
            $g_end   = gmdate('Ymd\THis\Z', $end_ts);
            $g_text  = urlencode("Cleaning: $service with $cleaner_name");
            $google_link = "https://calendar.google.com/calendar/render?action=TEMPLATE&text=$g_text&dates=$g_start/$g_end";
        }
    }
}

get_header(); 
?>

<style>
    .gb-success-page { background: #f8fafc; min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; font-family: 'Inter', sans-serif; }
    .gb-success-card { background: white; width: 100%; max-width: 500px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; text-align: center; border: 1px solid #e2e8f0; }
    .gb-success-header { background: #10b981; padding: 40px; color: white; }
    .gb-icon-circle { width: 80px; height: 80px; background: white; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 40px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    .gb-success-body { padding: 40px; }
    .gb-details-list { text-align: left; background: #f8fafc; border-radius: 12px; padding: 20px; margin: 30px 0; border: 1px solid #e2e8f0; }
    .gb-detail-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; color: #475569; }
    .gb-detail-row strong { color: #0f172a; font-weight: 600; }
    
    .gb-cal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 25px; }
    .gb-btn-cal { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0; transition: 0.2s; background: white; color: #334155; }
    .gb-btn-cal:hover { background: #f1f5f9; }

    .gb-account-box { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 20px; border-radius: 12px; margin-top: 30px; text-align: left; }
    .gb-account-title { font-weight: 800; color: #065f46; margin-bottom: 5px; display: block; }
    .gb-input-pwd { width: 100%; padding: 10px; border: 1px solid #6ee7b7; border-radius: 6px; margin-top: 10px; box-sizing: border-box; }
    .gb-btn-create { width: 100%; background: #059669; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 700; margin-top: 10px; cursor: pointer; }
    .gb-btn-create:hover { background: #047857; }
</style>

<?php
/*
 * The confirmation wording is the last thing a customer reads after paying,
 * and it was fixed in the template. It now reads from the page's meta, with
 * the previous text as the default.
 */
$b_paid_title = gb_page_text( '_gb_bs_paid_title', 'Booking Confirmed!', 'booking-success' );
$b_pend_title = gb_page_text( '_gb_bs_pending_title', 'Payment Pending', 'booking-success' );
$b_thanks     = gb_page_text( '_gb_bs_thanks', 'Thanks, {name}.', 'booking-success' );
$b_acct_sub   = gb_page_text( '_gb_bs_account_sub', 'Create a password to manage this appointment, reschedule, or cancel anytime.', 'booking-success' );
$b_acct_btn   = gb_page_text( '_gb_bs_account_btn', 'Create Account & View Dashboard', 'booking-success' );
$b_none       = gb_page_text( '_gb_bs_not_found', 'No Booking Found', 'booking-success' );
?>

<div class="gb-success-page">
    <div class="gb-success-card">
        
        <?php if($valid): ?>
            <div class="gb-success-header" style="background:<?php echo $is_paid ? '#10b981' : '#f59e0b'; ?>;">
                <div class="gb-icon-circle">✓</div>
                <h1 style="margin:0; font-size:24px; font-weight:800;"><?php echo esc_html($is_paid ? $b_paid_title : $b_pend_title); ?></h1>
                <p style="margin:10px 0 0; opacity:0.9;"><?php echo esc_html(str_replace('{name}', $name, $b_thanks)); ?></p>
            </div>
            
            <div class="gb-success-body">
                <?php if (!empty($payment_error)) : ?>
                    <div style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b; border-radius:10px; padding:12px; margin-bottom:16px; font-size:13px;">
                        <?php echo esc_html($payment_error); ?>
                    </div>
                <?php elseif (!empty($payment_notice)) : ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:10px; padding:12px; margin-bottom:16px; font-size:13px;">
                        <?php echo esc_html($payment_notice); ?>
                    </div>
                <?php endif; ?>

                <div class="gb-details-list">
                    <div class="gb-detail-row"><span>Service</span> <strong><?php echo esc_html($service); ?></strong></div>
                    <div class="gb-detail-row"><span>Professional</span> <strong><?php echo esc_html($cleaner_name); ?></strong></div>
                    <div class="gb-detail-row"><span>Date</span> <strong><?php echo date('l, M jS', strtotime($date)); ?></strong></div>
                    <div class="gb-detail-row"><span>Time</span> <strong><?php echo date('g:i A', strtotime($time)); ?> – <?php echo esc_html($end_time_display); ?></strong></div>
                    <div class="gb-detail-row"><span>Payment</span> <strong><?php echo $is_paid ? 'Paid' : 'Pending'; ?></strong></div>
                </div>

                <?php if ($is_paid): ?>
                    <div class="gb-cal-grid">
                        <a href="<?php echo esc_url($google_link); ?>" target="_blank" class="gb-btn-cal"><span class="dashicons dashicons-google"></span> Google Cal</a>
                        <a href="<?php echo esc_url(add_query_arg(['bid' => $booking_id, 't' => $access_token, 'download_ics' => 1], home_url('/booking-success'))); ?>" class="gb-btn-cal"><span class="dashicons dashicons-calendar-alt"></span> iCal</a>
                    </div>
                <?php endif; ?>
                
                <a href="<?php echo home_url(); ?>" style="color:#64748b; text-decoration:none; font-size:14px;">Return Home</a>

                <?php if ( $can_create_guest_account ): ?>
                    <form method="post" class="gb-account-box">
                        <input type="hidden" name="gb_create_account" value="1">
                        <?php wp_nonce_field('gb_create_account_' . $booking_id, 'gb_create_account_nonce'); ?>
                        <span class="gb-account-title">Don't lose this booking!</span>
                        <p style="margin:0; font-size:13px; color:#064e3b;"><?php echo esc_html($b_acct_sub); ?></p>
                        <?php echo $msg; ?>
                        <input type="password" name="new_password" class="gb-input-pwd" placeholder="Create a password..." required>
                        <p style="margin:10px 0 0; font-size:12px; color:#065f46;">Use at least 10 characters with upper and lower case letters plus a number.</p>
                        <button type="submit" class="gb-btn-create"><?php echo esc_html($b_acct_btn); ?></button>
                    </form>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="gb-success-body">
                <h2 style="color:#ef4444;"><?php echo esc_html($b_none); ?></h2>
                <a href="<?php echo home_url(); ?>" class="gb-btn-cal">Return Home</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php get_footer(); ?>
