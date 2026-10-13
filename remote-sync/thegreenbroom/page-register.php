<?php
/* Template Name: Custom Register */
if ( is_user_logged_in() ) { wp_safe_redirect( gb_get_post_login_redirect_url( wp_get_current_user() ) ); exit; }
$msg = '';

if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['gb_register']) ) {
    if (empty($_POST['gb_register_nonce']) || !wp_verify_nonce($_POST['gb_register_nonce'], 'gb_register_action')) {
        $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ Security check failed. Please refresh and try again.</div>';
    } else {
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $name = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : (isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '');
        $pass = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $type = isset($_POST['user_type']) ? sanitize_text_field(wp_unslash($_POST['user_type'])) : 'customer';
        $terms_accepted = ! empty($_POST['terms_acceptance']);
        $rate_state = gb_rate_limit_check('register', 5, HOUR_IN_SECONDS, $email);
        $password_check = gb_validate_account_password($pass);

        if ($rate_state['blocked']) {
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ ' . esc_html(gb_get_rate_limit_message($rate_state)) . '</div>';
        } elseif ( ! is_email($email) ) {
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ Please enter a valid email address.</div>';
        } elseif ( ! $terms_accepted ) {
            $agreement_name = $type === 'customer' ? "Subscribers' Terms of Association" : 'Terms of Business';
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ Please confirm that you have read and agree to the ' . esc_html( $agreement_name ) . '.</div>';
        } elseif ( is_wp_error($password_check) ) {
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ ' . esc_html($password_check->get_error_message()) . '</div>';
        } elseif ( email_exists($email) ) {
            gb_record_rate_limit_hit('register', HOUR_IN_SECONDS, $email);
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ Email already registered.</div>';
        } elseif ( $type === 'cleaner' && function_exists('gb_identity_email_belongs_to_account') && gb_identity_email_belongs_to_account($email) ) {
            /*
             * The same inbox written differently: J.Smith@gmail.com,
             * jsmith+2@googlemail.com and jsmith@gmail.com all deliver to one
             * person. A second cleaner account from it was how the free three
             * months were being taken again, so it is refused here and they are
             * pointed at the account they already have.
             */
            gb_record_rate_limit_hit('register', HOUR_IN_SECONDS, $email);
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">It looks like you already have an account. <a href="' . esc_url(home_url('/login/')) . '">Log in</a> or <a href="' . esc_url(home_url('/lost-password/')) . '">reset your password</a>.</div>';
        } else {
            gb_record_rate_limit_hit('register', HOUR_IN_SECONDS, $email);
            $uid = wp_create_user($email, $pass, $email);
            if(!is_wp_error($uid)) {
                wp_update_user(['ID'=>$uid, 'first_name'=>$name, 'display_name'=>$name]);
                update_user_meta($uid, '_gb_terms_accepted', '1');
                update_user_meta($uid, '_gb_terms_accepted_at', current_time('mysql'));
                if ($type === 'customer') {
                    update_user_meta($uid, '_gb_subscriber_terms_accepted', '1');
                    update_user_meta($uid, '_gb_subscriber_terms_accepted_at', current_time('mysql'));
                    update_user_meta($uid, '_gb_subscriber_terms_accepted_version', function_exists('gb_get_subscriber_terms_of_association_version') ? gb_get_subscriber_terms_of_association_version() : 'current');
                }
                $u = new WP_User($uid);

                // They clicked a price before they had an account. Hold on to
                // it so their account page can offer it back, rather than
                // losing the thing they were actually trying to do.
                $chosen_plan = isset($_POST['gb_plan']) ? sanitize_key(wp_unslash($_POST['gb_plan'])) : '';
                if ($chosen_plan !== '' && function_exists('gb_membership_set_pending_plan')) {
                    gb_membership_set_pending_plan($uid, $chosen_plan);
                }
                
                if($type === 'cleaner') {
                    $u->set_role('gb_cleaner');
                    $pid = wp_insert_post(['post_title'=>$name, 'post_type'=>'gb_cleaner', 'post_status'=>'draft', 'post_author'=>$uid]);
                    update_post_meta($pid, 'hourly_rate', '15');
                    gb_sync_cleaner_verification_state($pid, 'draft');
                    // Fingerprint them (sign-up IP included, which is only
                    // known now) and flag a likely second account. Allowed
                    // through either way: see inc/identity-guard.php.
                    if (function_exists('gb_identity_on_cleaner_registered')) {
                        gb_identity_on_cleaner_registered($uid);
                    }
                    wp_set_current_user($uid); wp_set_auth_cookie($uid);
                    $verification_state = gb_prepare_new_user_email_verification($uid, home_url('/verify-profile'));
                    wp_safe_redirect($verification_state['redirect']); exit;
                } else {
                    $u->set_role('gb_customer');
                    wp_set_current_user($uid); wp_set_auth_cookie($uid);
                    $verification_state = gb_prepare_new_user_email_verification($uid, home_url('/my-account/'));
                    wp_safe_redirect($verification_state['redirect']); exit;
                }
            }

            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ We could not create your account. Please try again.</div>';
        }
    }
}
get_header(); 
?>

<main id="main-content" class="gb-auth-wrapper">
    <div class="gb-card gb-auth-card">
        <h1 class="text-center">Create Account</h1>
        <p class="text-center text-muted mb-20">Join The Green Broom today.</p>
        <?php echo $msg; ?>

        <form method="post">
            <input type="hidden" name="gb_register" value="1">
            <?php wp_nonce_field('gb_register_action', 'gb_register_nonce'); ?>
            <?php
            // Carried through from a price they clicked while logged out.
            $gb_reg_plan = isset($_GET['plan']) ? sanitize_key(wp_unslash($_GET['plan'])) : '';
            $gb_reg_cat  = function_exists('gb_subscription_catalogue') ? gb_subscription_catalogue() : [];
            if ($gb_reg_plan !== '' && isset($gb_reg_cat[$gb_reg_plan])) :
            ?>
                <input type="hidden" name="gb_plan" value="<?php echo esc_attr($gb_reg_plan); ?>">
                <p class="gb-reg-plan-note">Creating your account first &mdash; we will pick up <strong><?php echo esc_html($gb_reg_cat[$gb_reg_plan]['label']); ?></strong> straight after.</p>
            <?php endif; ?>
            <div class="gb-form-group">
                <label class="gb-label" for="register-user-type">I want to...</label>
                <select id="register-user-type" name="user_type" class="gb-input">
                    <option value="customer">Book a Clean</option>
                    <option value="cleaner">Work as Cleaner</option>
                </select>
            </div>
            <div class="gb-form-group"><label class="gb-label" for="register-full-name">Full Name</label><input id="register-full-name" type="text" name="full_name" class="gb-input" required autocomplete="name"></div>
            <div class="gb-form-group"><label class="gb-label" for="register-email">Email Address</label><input id="register-email" type="email" name="email" class="gb-input" required autocomplete="email"></div>
            <?php
            /*
             * What a cleaner applicant is told here has to match what the
             * pricing panel on Become a Cleaner promised them a click earlier,
             * so the free-trial line is read from the same stored field the
             * panel prints. Edit it once in Settings and both agree.
             *
             * "Booking link coming soon" was a leftover placeholder on a live
             * page and is gone.
             */
            $gb_reg_free = function_exists('gb_pricing_field') ? trim((string) gb_pricing_field('_gb_price_clean_free')) : '';
            $gb_reg_show_free = $gb_reg_free !== '' && (!function_exists('gb_pricing_panel_enabled') || gb_pricing_panel_enabled('cleaner'));
            ?>
            <p id="gb-cleaner-signup-note" style="display:none; margin:0 0 18px; padding:12px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; color:#166534; font-size:13px; line-height:1.5;"><?php if ($gb_reg_show_free): ?><strong><?php echo esc_html($gb_reg_free); ?>.</strong> Nothing to pay today. <?php endif; ?>Cleaner applications include email references and a 15-minute WhatsApp or FaceTime call.</p>
            <div class="gb-form-group">
                <label class="gb-label" for="register-password">Password</label>
                <input id="register-password" type="password" name="password" class="gb-input" required autocomplete="new-password" aria-describedby="register-password-help">
                <p id="register-password-help" style="margin:8px 0 0; font-size:12px; color:#64748b;">Use at least 10 characters with upper and lower case letters plus a number.</p>
            </div>
            <label style="display:flex; gap:10px; align-items:flex-start; margin:0 0 18px; font-size:13px; line-height:1.5; color:#334155;">
                <input type="checkbox" name="terms_acceptance" value="1" required style="margin-top:3px;">
                <span id="gb-terms-acceptance-copy">I have read and agree to the <a id="gb-terms-acceptance-link" href="<?php echo esc_url(function_exists('gb_get_subscriber_terms_page_url') ? gb_get_subscriber_terms_page_url() : home_url('/subscribers-terms-of-association/')); ?>" target="_blank" rel="noopener">Subscribers' Terms of Association</a>.</span>
            </label>
            <button type="submit" class="gb-btn gb-btn-primary gb-btn-block">Sign Up</button>
        </form>
        
        <p class="text-center mt-20" style="font-size:0.9rem;">
            Already have an account? <a href="<?php echo home_url('/login'); ?>">Log In</a>
        </p>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeSelect = document.querySelector('select[name="user_type"]');
    var cleanerNote = document.getElementById('gb-cleaner-signup-note');
    var termsLink = document.getElementById('gb-terms-acceptance-link');
    if (!typeSelect || !cleanerNote || !termsLink) return;
    var updateCleanerNote = function () {
        var isCleaner = typeSelect.value === 'cleaner';
        cleanerNote.style.display = isCleaner ? 'block' : 'none';
        termsLink.href = isCleaner ? '<?php echo esc_url(function_exists('gb_get_terms_page_url') ? gb_get_terms_page_url() : home_url('/terms/')); ?>' : '<?php echo esc_url(function_exists('gb_get_subscriber_terms_page_url') ? gb_get_subscriber_terms_page_url() : home_url('/subscribers-terms-of-association/')); ?>';
        termsLink.textContent = isCleaner ? 'Terms of Business' : "Subscribers' Terms of Association";
    };
    typeSelect.addEventListener('change', updateCleanerNote);
    updateCleanerNote();
});
</script>
<?php get_footer(); ?>
