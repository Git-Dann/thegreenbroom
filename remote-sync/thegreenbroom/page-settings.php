<?php
/* Template Name: Account Settings */

if ( ! is_user_logged_in() ) { wp_redirect( home_url('/login') ); exit; }
get_header();
?>
<style>
    .gb-settings-head { display: flex; flex-direction: column; align-items: center; gap: 14px; }
    .gb-settings-back { align-self: flex-start; color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; opacity: .9; }
    .gb-settings-back:hover { opacity: 1; color: #fff; }
    .gb-settings-head h1 { font-size: 32px; font-weight: 800; margin: 0; letter-spacing: -1.2px; text-align: center; color: #fff; }

    /* Four cells at 1fr 1fr never collapsed; one of them prints the Stripe
       account id, which is long enough to break out of the card. */
    .gb-stripe-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; font-size: 12px; overflow-wrap: anywhere; }

    @media (max-width: 640px) {
        .gb-settings-head h1 { font-size: 24px; letter-spacing: -.6px; }
        .gb-stripe-grid { grid-template-columns: 1fr; }
    }
</style>
<?php
 

$current_user = wp_get_current_user();

// Fetch cleaner data for subscription status
$args = array(
    'post_type' => 'gb_cleaner', 
    'author' => $current_user->ID, 
    'posts_per_page' => 1, 
    'post_status' => ['publish', 'pending', 'draft']
);
$cleaner_query = new WP_Query($args);

$sub_status = 'trial';
$renewal_date = 'N/A';
$cleaner_id = 0;

$stripe_account_id = '';
$stripe_connect_status = 'not_connected';
$stripe_charges_enabled = false;
$stripe_payouts_enabled = false;
$stripe_payout_interval = 'daily';
$stripe_config_error = '';
$stripe_live_error = '';

if ( $cleaner_query->have_posts() ) {
    $cleaner_id = $cleaner_query->posts[0]->ID;
    $sub_status = get_post_meta($cleaner_id, '_gb_subscription_status', true) ?: 'trial';
    $renewal_date = get_post_meta($cleaner_id, '_gb_sub_renewal', true) ?: 'N/A';

    $stripe_account_id = sanitize_text_field((string) get_post_meta($cleaner_id, '_gb_stripe_account_id', true));
    $stripe_connect_status = get_post_meta($cleaner_id, '_gb_stripe_connect_status', true) ?: 'not_connected';
    $stripe_charges_enabled = get_post_meta($cleaner_id, '_gb_stripe_charges_enabled', true) === '1';
    $stripe_payouts_enabled = get_post_meta($cleaner_id, '_gb_stripe_payouts_enabled', true) === '1';
    $stripe_payout_interval = function_exists('gb_normalize_payout_interval')
        ? gb_normalize_payout_interval(get_post_meta($cleaner_id, '_gb_stripe_payout_interval', true))
        : 'daily';
}

$settings_page_url = get_permalink() ?: home_url('/settings/');
$connect_notice = isset($_GET['connect']) ? sanitize_key(wp_unslash($_GET['connect'])) : '';
$connect_notice_msg = isset($_GET['connect_msg']) ? sanitize_text_field(wp_unslash($_GET['connect_msg'])) : '';

if (function_exists('gb_require_stripe_config')) {
    $stripe_config_check = gb_require_stripe_config(['secret_key']);
    if (is_wp_error($stripe_config_check)) {
        $stripe_config_error = $stripe_config_check->get_error_message();
    }
}

if ($cleaner_id && $stripe_account_id !== '' && $stripe_config_error === '' && function_exists('gb_get_cleaner_connect_status_live')) {
    $live_status = gb_get_cleaner_connect_status_live($cleaner_id);
    if (is_wp_error($live_status)) {
        $stripe_live_error = $live_status->get_error_message();
    } else {
        $stripe_connect_status = get_post_meta($cleaner_id, '_gb_stripe_connect_status', true) ?: $stripe_connect_status;
        $stripe_charges_enabled = get_post_meta($cleaner_id, '_gb_stripe_charges_enabled', true) === '1';
        $stripe_payouts_enabled = get_post_meta($cleaner_id, '_gb_stripe_payouts_enabled', true) === '1';
        $stripe_payout_interval = function_exists('gb_normalize_payout_interval')
            ? gb_normalize_payout_interval(get_post_meta($cleaner_id, '_gb_stripe_payout_interval', true))
            : $stripe_payout_interval;
    }
}

$connect_labels = [
    'active' => 'Payments Active',
    'pending_review' => 'Payments Pending',
    'restricted' => 'Action Required',
    'needs_onboarding' => 'Complete Onboarding',
    'not_connected' => 'Not Connected',
];
$connect_label = isset($connect_labels[$stripe_connect_status]) ? $connect_labels[$stripe_connect_status] : ucwords(str_replace('_', ' ', $stripe_connect_status));
$connect_is_active = ($stripe_connect_status === 'active' && $stripe_charges_enabled);
$connect_badge_style = $connect_is_active ? 'background:#dcfce7; color:#166534;' : ($stripe_connect_status === 'restricted' ? 'background:#fee2e2; color:#991b1b;' : 'background:#fffbeb; color:#92400e;');

$connect_actions_enabled = $cleaner_id && $stripe_config_error === '';
$connect_onboarding_url = $connect_actions_enabled ? wp_nonce_url(
    gb_connect_action_url('gb_connect_onboarding', $cleaner_id, $settings_page_url),
    'gb_connect_onboarding_' . $cleaner_id
) : '#';
$connect_login_url = $connect_actions_enabled ? wp_nonce_url(
    gb_connect_action_url('gb_connect_login_link', $cleaner_id, $settings_page_url),
    'gb_connect_login_' . $cleaner_id
) : '#';
$connect_sync_url = $connect_actions_enabled ? wp_nonce_url(
    gb_connect_action_url('gb_connect_sync', $cleaner_id, $settings_page_url),
    'gb_connect_sync_' . $cleaner_id
) : '#';
?>

<div class="gb-page-wrapper" style="background: #f8fafc; min-height: 100vh; padding-top: 0; padding-bottom: 60px;">
    
    <div style="background: #1b5e20; padding: 40px 0 90px; color: #fff;">
        <?php /* The back link was absolutely positioned across a centred 32px
                 title, so on a phone the two overlapped. Stacked instead. */ ?>
        <div class="gb-container gb-settings-head">
            <a class="gb-settings-back" href="<?php echo home_url('/cleaner-dashboard'); ?>">&larr; Back to Dashboard</a>
            <h1>Account Settings</h1>
        </div>
    </div>

    <div class="gb-container" style="margin-top: -50px; max-width: 600px; position: relative; z-index: 10;">

        <?php echo gb_password_notice_html(); ?>

        <?php if ($connect_notice === 'error' && $connect_notice_msg !== ''): ?>
            <div class="gb-card mb-20" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;">
                Stripe Connect: <?php echo esc_html($connect_notice_msg); ?>
            </div>
        <?php elseif (in_array($connect_notice, ['returned', 'synced'], true)): ?>
            <div class="gb-card mb-20" style="background:#f0fdf4; border-color:#dcfce7; color:#166534;">
                Stripe Connect <?php echo $connect_notice === 'synced' ? 'status synced.' : 'onboarding progress updated.'; ?>
            </div>
        <?php endif; ?>
        
        <div class="gb-card mb-20" style="padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 20px;">Membership & Billing</h3>
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Current Status</span>
                    <span style="background: #fffbeb; color: #92400e; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase;">
                        <?php echo esc_html($sub_status); ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Renewal Date</span>
                    <span style="font-size: 14px; font-weight: 700; color: #0f172a;"><?php echo esc_html($renewal_date); ?></span>
                </div>
            </div>

            <a href="<?php echo esc_url(home_url('/contact-support')); ?>" class="gb-btn-auth" style="display:block; text-align:center; text-decoration:none; box-sizing:border-box; background: #2e7d32; border: none; color: #fff; padding: 14px; border-radius: 8px; font-weight: 700; width: 100%; cursor: pointer;">
                Contact the Team About Your Plan
            </a>
            <p style="margin:12px 0 0; color:#64748b; font-size:12px; line-height:1.5;">Membership plan changes are managed by the Green Broom team. Contact support to upgrade, downgrade or pause your membership; self-service billing is coming soon.</p>
        </div>

        <div class="gb-card mb-20" style="padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin:0;">Stripe Connect</h3>
                <span style="<?php echo esc_attr($connect_badge_style); ?> padding:4px 10px; border-radius:4px; font-size:10px; font-weight:800; text-transform:uppercase;"><?php echo esc_html($connect_label); ?></span>
            </div>

            <?php if ($stripe_config_error !== ''): ?>
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px; color:#92400e; font-size:13px; margin-bottom:15px;">
                    Payments are temporarily unavailable. Please contact support.
                </div>
            <?php elseif ($stripe_live_error !== ''): ?>
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px; color:#92400e; font-size:13px; margin-bottom:15px;">
                    Could not refresh your Stripe status right now: <?php echo esc_html($stripe_live_error); ?>
                </div>
            <?php endif; ?>

            <div class="gb-stripe-grid">
                <div>Charges: <strong><?php echo $stripe_charges_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
                <div>Payouts: <strong><?php echo $stripe_payouts_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
                <div>Payout schedule: <strong><?php echo esc_html(ucfirst($stripe_payout_interval)); ?></strong></div>
                <div>Account: <strong><?php echo esc_html($stripe_account_id !== '' ? $stripe_account_id : 'Not connected'); ?></strong></div>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <?php if ($connect_actions_enabled): ?>
                    <a href="<?php echo esc_url($connect_onboarding_url); ?>" class="gb-btn" style="background:#2e7d32; color:#fff; border:none; padding:10px 14px; border-radius:6px; font-weight:700; text-decoration:none;"><?php echo $stripe_account_id ? 'Continue Setup' : 'Start Setup'; ?></a>
                <?php else: ?>
                    <span class="gb-btn" style="background:#e2e8f0; color:#64748b; border:none; padding:10px 14px; border-radius:6px; font-weight:700;">Unavailable</span>
                <?php endif; ?>

                <?php if ($stripe_account_id !== '' && $connect_actions_enabled): ?>
                    <a href="<?php echo esc_url($connect_login_url); ?>" class="gb-btn" style="background:#fff; color:#334155; border:1px solid #d1d5db; padding:10px 14px; border-radius:6px; font-weight:700; text-decoration:none;">Open Stripe</a>
                    <a href="<?php echo esc_url($connect_sync_url); ?>" class="gb-btn" style="background:#fff; color:#334155; border:1px solid #d1d5db; padding:10px 14px; border-radius:6px; font-weight:700; text-decoration:none;">Sync</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="gb-card mb-20" style="padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 20px;">Change Password</h3>
            <form method="post" action="<?php echo esc_url($settings_page_url); ?>">
                <input type="hidden" name="gb_pw_action" value="change_password">
                <input type="hidden" name="gb_pw_redirect" value="<?php echo esc_url($settings_page_url); ?>">
                <?php wp_nonce_field('gb_change_password', 'gb_pw_nonce'); ?>
                <div style="margin-bottom: 15px;">
                    <input type="password" name="gb_current_password" placeholder="Current Password" required autocomplete="current-password" style="width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 15px;">
                    <input type="password" name="gb_new_password" placeholder="New Password (min. 8 characters)" required minlength="8" autocomplete="new-password" style="width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 20px;">
                    <input type="password" name="gb_confirm_password" placeholder="Confirm New Password" required minlength="8" autocomplete="new-password" style="width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <button type="submit" style="background: #2e7d32; border: none; color: #fff; padding: 14px; border-radius: 8px; font-weight: 700; width: 100%; cursor: pointer;">Update Password</button>
            </form>
        </div>

        <div class="gb-card" style="padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; border-left: 4px solid #dc2626; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h3 style="font-size: 18px; font-weight: 800; color: #dc2626; margin-bottom: 15px;">Danger Zone</h3>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 20px; line-height: 1.5;">Account deletion is permanent and removes your profile and listings. To protect against accidental loss and keep booking/payment records compliant, deletions are handled by our team on request.</p>
            <a href="<?php echo esc_url(add_query_arg('subject', rawurlencode('Account deletion request'), home_url('/contact-support'))); ?>" class="gb-btn" style="display:inline-block; text-decoration:none; background: #fff; color: #dc2626; border: 1px solid #dc2626; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer;">Request Account Deletion</a>
        </div>

    </div>
</div>

<?php get_footer(); ?>
