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
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ Please confirm that you have read and agree to the Terms of Business.</div>';
        } elseif ( is_wp_error($password_check) ) {
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ ' . esc_html($password_check->get_error_message()) . '</div>';
        } elseif ( email_exists($email) ) {
            gb_record_rate_limit_hit('register', HOUR_IN_SECONDS, $email);
            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ Email already registered.</div>';
        } else {
            gb_record_rate_limit_hit('register', HOUR_IN_SECONDS, $email);
            $uid = wp_create_user($email, $pass, $email);
            if(!is_wp_error($uid)) {
                wp_update_user(['ID'=>$uid, 'first_name'=>$name, 'display_name'=>$name]);
                update_user_meta($uid, '_gb_terms_accepted', '1');
                update_user_meta($uid, '_gb_terms_accepted_at', current_time('mysql'));
                $u = new WP_User($uid);
                
                if($type === 'cleaner') {
                    $u->set_role('gb_cleaner');
                    $pid = wp_insert_post(['post_title'=>$name, 'post_type'=>'gb_cleaner', 'post_status'=>'draft', 'post_author'=>$uid]);
                    update_post_meta($pid, 'hourly_rate', '15');
                    gb_sync_cleaner_verification_state($pid, 'draft');
                    wp_set_current_user($uid); wp_set_auth_cookie($uid);
                    $verification_state = gb_prepare_new_user_email_verification($uid, home_url('/verify-profile'));
                    wp_safe_redirect($verification_state['redirect']); exit;
                } else {
                    $u->set_role('gb_customer');
                    wp_set_current_user($uid); wp_set_auth_cookie($uid);
                    $verification_state = gb_prepare_new_user_email_verification($uid, home_url('/my-account'));
                    wp_safe_redirect($verification_state['redirect']); exit;
                }
            }

            $msg = '<div class="gb-badge gb-badge-danger" style="display:block; margin-bottom:15px;">⚠️ We could not create your account. Please try again.</div>';
        }
    }
}
get_header(); 
?>

<div class="gb-auth-wrapper">
    <div class="gb-card gb-auth-card">
        <h1 class="text-center">Create Account</h1>
        <p class="text-center text-muted mb-20">Join The Green Broom today.</p>
        <?php echo $msg; ?>

        <form method="post">
            <input type="hidden" name="gb_register" value="1">
            <?php wp_nonce_field('gb_register_action', 'gb_register_nonce'); ?>
            <div class="gb-form-group">
                <label class="gb-label">I want to...</label>
                <select name="user_type" class="gb-input">
                    <option value="customer">Book a Clean</option>
                    <option value="cleaner">Work as Cleaner</option>
                </select>
            </div>
            <div class="gb-form-group"><label class="gb-label">Full Name</label><input type="text" name="full_name" class="gb-input" required></div>
            <div class="gb-form-group"><label class="gb-label">Email Address</label><input type="email" name="email" class="gb-input" required></div>
            <p id="gb-cleaner-signup-note" style="display:none; margin:0 0 18px; padding:12px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; color:#166534; font-size:13px; line-height:1.5;">Cleaner applications include email references and a 15-minute WhatsApp or FaceTime call. Booking link coming soon.</p>
            <div class="gb-form-group">
                <label class="gb-label">Password</label>
                <input type="password" name="password" class="gb-input" required>
                <p style="margin:8px 0 0; font-size:12px; color:#64748b;">Use at least 10 characters with upper and lower case letters plus a number.</p>
            </div>
            <label style="display:flex; gap:10px; align-items:flex-start; margin:0 0 18px; font-size:13px; line-height:1.5; color:#334155;">
                <input type="checkbox" name="terms_acceptance" value="1" required style="margin-top:3px;">
                <span>I have read and agree to the <a href="<?php echo esc_url(function_exists('gb_get_terms_page_url') ? gb_get_terms_page_url() : home_url('/terms/')); ?>" target="_blank" rel="noopener">Terms of Business</a>.</span>
            </label>
            <button type="submit" class="gb-btn gb-btn-primary gb-btn-block">Sign Up</button>
        </form>
        
        <p class="text-center mt-20" style="font-size:0.9rem;">
            Already have an account? <a href="<?php echo home_url('/login'); ?>">Log In</a>
        </p>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeSelect = document.querySelector('select[name="user_type"]');
    var cleanerNote = document.getElementById('gb-cleaner-signup-note');
    if (!typeSelect || !cleanerNote) return;
    var updateCleanerNote = function () { cleanerNote.style.display = typeSelect.value === 'cleaner' ? 'block' : 'none'; };
    typeSelect.addEventListener('change', updateCleanerNote);
    updateCleanerNote();
});
</script>
<?php get_footer(); ?>
