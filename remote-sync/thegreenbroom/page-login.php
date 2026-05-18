<?php
/* Template Name: Custom Login */

if ( is_user_logged_in() ) { wp_safe_redirect( gb_get_post_login_redirect_url( wp_get_current_user() ) ); exit; }
$error_msg = '';
$requested_redirect = isset( $_REQUEST['redirect_to'] ) ? gb_normalize_internal_redirect_url( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['gb_login']) ) {
    if (empty($_POST['gb_login_nonce']) || !wp_verify_nonce($_POST['gb_login_nonce'], 'gb_login_action')) {
        $error_msg = '<div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #fee2e2; text-align: center;">❌ Security check failed. Please refresh and try again.</div>';
    } else {
        $login_identity = isset($_POST['log_identity']) ? sanitize_text_field(wp_unslash($_POST['log_identity'])) : '';
        $rate_state = gb_rate_limit_check('login', 8, 15 * MINUTE_IN_SECONDS, strtolower($login_identity));

        if ($rate_state['blocked']) {
            $error_msg = '<div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #fee2e2; text-align: center;">❌ ' . esc_html(gb_get_rate_limit_message($rate_state)) . '</div>';
        } else {
            $creds = [
                'user_login'    => $login_identity,
                'user_password' => isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '',
                'remember'      => true
            ];

            $user = wp_signon( $creds, false );

            if ( is_wp_error( $user ) ) {
                gb_record_rate_limit_hit('login', 15 * MINUTE_IN_SECONDS, strtolower($login_identity));
                $error_msg = '<div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; border: 1px solid #fee2e2; text-align: center;">❌ Invalid Username or Password</div>';
            } else {
                gb_reset_rate_limit('login', strtolower($login_identity));
                $redirect_url = gb_get_post_login_redirect_url( $user );
                if ( ! gb_user_requires_email_verification( $user ) && $requested_redirect !== '' ) {
                    $redirect_url = $requested_redirect;
                }
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }
}
get_header(); 
?>

<style>
    /* Optimized Layout: 
       Restores standard width but removes massive surrounding whitespace 
    */
    .gb-auth-page-wrap { 
        background: #f8fafc; 
        min-height: 60vh; /* Reduced from 100vh to sit higher on the page */
        display: flex; 
        flex-direction: column;
        align-items: center; 
        justify-content: center; 
        padding: 80px 20px; /* Balanced padding for professional breathing room */
    }

    .gb-auth-card-main { 
        width: 100%; 
        max-width: 400px; /* Restored to original robust width */
        background: #fff; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        overflow: hidden; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    /* Seamless Header */
    .gb-auth-card-head { 
        border-bottom: 1px solid #f1f5f9; 
        padding: 24px 20px; 
        text-align: center;
    }
    .gb-auth-card-head h1 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.5px; }
    .gb-auth-card-head p { font-size: 13px; color: #64748b; margin: 5px 0 0; }

    .gb-auth-card-body { padding: 30px; }

    /* OS Input Styles */
    .gb-os-label-alt { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .gb-os-input-auth { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #0f172a; font-family: inherit; margin-bottom: 20px; box-sizing: border-box; }
    .gb-os-input-auth:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.05); }

    .gb-login-btn-final { 
        width: 100%; 
        background: #2e7d32; 
        color: #fff; 
        border: none; 
        padding: 14px; 
        border-radius: 8px; 
        font-weight: 700; 
        font-size: 14px; 
        cursor: pointer; 
        transition: background 0.2s;
    }
    .gb-login-btn-final:hover { background: #1b5e20; }

    /* Footer Nav Underneath the Box */
    .gb-auth-footer-nav { margin-top: 25px; text-align: center; font-size: 13px; }
    .gb-auth-footer-nav a { color: #2e7d32; font-weight: 700; text-decoration: none; border-bottom: 1px solid rgba(46, 125, 50, 0.2); }
    .gb-auth-footer-nav a:hover { border-bottom-color: #2e7d32; }
</style>

<div class="gb-auth-page-wrap">
    
    <div class="gb-auth-card-main">
        <div class="gb-auth-card-head">
            <h1>Welcome</h1>
            <p>Login to access your dashboard</p>
        </div>

        <div class="gb-auth-card-body">
            <?php echo $error_msg; ?>

            <form method="post">
                <input type="hidden" name="gb_login" value="1">
                <?php wp_nonce_field('gb_login_action', 'gb_login_nonce'); ?>
                <?php if ( $requested_redirect !== '' ) : ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $requested_redirect ); ?>">
                <?php endif; ?>
                
                <div class="gb-form-row">
                    <label class="gb-os-label-alt">Username or Email</label>
                    <input type="text" name="log_identity" class="gb-os-input-auth" placeholder="e.g. John Smith" required>
                </div>

                <div class="gb-form-row">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label class="gb-os-label-alt" style="margin-bottom: 0;">Password</label>
                        <a href="<?php echo wp_lostpassword_url(); ?>" style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; text-decoration: none;">Lost?</a>
                    </div>
                    <input type="password" name="password" class="gb-os-input-auth" required>
                </div>

                <button type="submit" class="gb-login-btn-final">Secure Login</button>
            </form>
        </div>
    </div>

    <div class="gb-auth-footer-nav">
        <span style="color: #64748b;">Don't have an account?</span> 
        <a href="<?php echo home_url('/register'); ?>">Create an account instead</a>
    </div>

</div>

<?php get_footer(); ?>
