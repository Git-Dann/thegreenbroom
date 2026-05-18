<?php
/* Template Name: Lost Password */

if ( is_user_logged_in() ) { wp_safe_redirect( gb_get_post_login_redirect_url( wp_get_current_user() ) ); exit; }

$lost_password_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_login'])) {
    if (empty($_POST['gb_lostpassword_nonce']) || !wp_verify_nonce($_POST['gb_lostpassword_nonce'], 'gb_lostpassword_action')) {
        $lost_password_error = 'Security check failed. Please refresh and try again.';
    } else {
        $user_login = sanitize_text_field(wp_unslash($_POST['user_login']));
        $rate_state = gb_rate_limit_check('lost_password', 5, HOUR_IN_SECONDS, strtolower($user_login));

        if ($rate_state['blocked']) {
            $lost_password_error = gb_get_rate_limit_message($rate_state);
        } else {
            gb_record_rate_limit_hit('lost_password', HOUR_IN_SECONDS, strtolower($user_login));
            $result = retrieve_password($user_login);
            if (true === $result) {
                wp_safe_redirect(add_query_arg('checkemail', 'confirm', home_url('/lost-password')));
                exit;
            }

            if (is_wp_error($result)) {
                $lost_password_error = $result->get_error_message();
            } else {
                $lost_password_error = 'We could not send a reset email right now. Please try again.';
            }
        }
    }
}

get_header(); 
?>

<style>
    .gb-auth-page-wrap { 
        background: #f8fafc; 
        min-height: 60vh; 
        display: flex; 
        flex-direction: column;
        align-items: center; 
        justify-content: center; 
        padding: 80px 20px; 
    }

    .gb-auth-card-main { 
        width: 100%; 
        max-width: 400px; 
        background: #fff; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        overflow: hidden; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .gb-auth-card-head { 
        border-bottom: 1px solid #f1f5f9; 
        padding: 24px 20px; 
        text-align: center;
    }
    .gb-auth-card-head h1 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.5px; }
    .gb-auth-card-head p { font-size: 13px; color: #64748b; margin: 5px 0 0; }

    .gb-auth-card-body { padding: 30px; }

    .gb-os-label-alt { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .gb-os-input-auth { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #0f172a; font-family: inherit; margin-bottom: 20px; box-sizing: border-box; }

    .gb-btn-final { 
        width: 100%; background: #2e7d32; color: #fff; border: none; padding: 14px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; 
    }
</style>

<div class="gb-auth-page-wrap">
    <div class="gb-auth-card-main">
        <div class="gb-auth-card-head">
            <h1>Reset Password</h1>
            <p>We'll email you a reset link</p>
        </div>

        <div class="gb-auth-card-body">
            <?php 
            if ( isset($_GET['checkemail']) && $_GET['checkemail'] == 'confirm' ) {
                echo '<div style="background:#f0fdf4; color:#166534; padding:12px; border-radius:8px; font-size:13px; margin-bottom:20px; text-align:center;">📩 Check your email for the reset link.</div>';
            }
            if ( ! empty($lost_password_error) ) {
                echo '<div style="background:#fef2f2; color:#991b1b; padding:12px; border-radius:8px; font-size:13px; margin-bottom:20px; text-align:center;">' . esc_html($lost_password_error) . '</div>';
            }
            ?>

            <form method="post" action="<?php echo esc_url(home_url('/lost-password')); ?>">
                <?php wp_nonce_field('gb_lostpassword_action', 'gb_lostpassword_nonce'); ?>
                <div class="gb-form-row">
                    <label class="gb-os-label-alt">Username or Email</label>
                    <input type="text" name="user_login" class="gb-os-input-auth" placeholder="e.g. John Smith" required>
                </div>
                <button type="submit" class="gb-btn-final">Send Reset Link</button>
            </form>
        </div>
    </div>

    <div style="margin-top: 25px; text-align: center; font-size: 13px;">
        <a href="<?php echo home_url('/login'); ?>" style="color: #2e7d32; font-weight: 700; text-decoration: none;">← Back to Login</a>
    </div>
</div>

<?php get_footer(); ?>
