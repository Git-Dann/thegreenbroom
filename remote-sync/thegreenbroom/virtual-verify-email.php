<?php
$email_status = isset( $_GET['email_status'] ) ? sanitize_key( wp_unslash( $_GET['email_status'] ) ) : '';
$status_message = '';
$status_tone = 'info';
$current_user = is_user_logged_in() ? wp_get_current_user() : null;
$view_user = ( $current_user instanceof WP_User && $current_user->exists() ) ? $current_user : null;
$verification_email = $view_user instanceof WP_User ? $view_user->user_email : '';
$continue_url = $view_user instanceof WP_User ? gb_get_user_dashboard_url( $view_user ) : home_url( '/login' );
$continue_label = $view_user instanceof WP_User ? 'Continue to your dashboard' : 'Go to login';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['gb_resend_email_verification'] ) ) {
    if ( empty( $_POST['gb_verify_email_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['gb_verify_email_nonce'] ), 'gb_resend_email_verification' ) ) {
        $email_status = 'send-error';
        $status_tone = 'error';
        $status_message = 'Security check failed. Please refresh and try again.';
    } elseif ( ! ( $current_user instanceof WP_User ) || ! $current_user->exists() ) {
        $email_status = 'prompt-login';
    } elseif ( ! gb_user_requires_email_verification( $current_user ) ) {
        $email_status = 'already-verified';
        $continue_url = gb_get_user_dashboard_url( $current_user );
    } else {
        $send_result = gb_send_email_verification( $current_user->ID, true );
        if ( is_wp_error( $send_result ) ) {
            $email_status = $send_result->get_error_code() === 'gb_email_rate_limited' ? 'rate-limited' : 'send-error';
            $status_tone = 'error';
            $status_message = $send_result->get_error_message();
        } else {
            $email_status = 'resent';
        }
    }
}

if ( isset( $_GET['uid'], $_GET['token'] ) ) {
    $user_id = absint( wp_unslash( $_GET['uid'] ) );
    $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
    $token_user = get_user_by( 'id', $user_id );

    if ( $token_user instanceof WP_User && $token_user->exists() ) {
        $view_user = $token_user;
        $verification_email = $token_user->user_email;
    }

    $verify_result = gb_verify_user_email_token( $user_id, $token );
    if ( is_wp_error( $verify_result ) ) {
        $email_status = $verify_result->get_error_code() === 'gb_expired_verification' ? 'expired' : 'invalid';
        $status_tone = 'error';
        $status_message = $verify_result->get_error_message();
    } else {
        $email_status = 'verified';
        if ( $token_user instanceof WP_User && $token_user->exists() ) {
            $success_redirect = gb_consume_email_verification_success_redirect( $token_user );
            if ( $current_user instanceof WP_User && $current_user->exists() && $current_user->ID === $token_user->ID ) {
                $continue_url = $success_redirect;
                $continue_label = 'Continue';
            } else {
                $continue_url = add_query_arg( 'redirect_to', $success_redirect, home_url( '/login' ) );
                $continue_label = 'Log in to continue';
            }
        }
    }
}

if ( $email_status === '' ) {
    if ( $view_user instanceof WP_User && gb_user_requires_email_verification( $view_user ) ) {
        $email_status = 'check-inbox';
    } elseif ( $view_user instanceof WP_User ) {
        $email_status = 'already-verified';
        $continue_url = gb_get_user_dashboard_url( $view_user );
    } else {
        $email_status = 'prompt-login';
    }
}

if ( $verification_email === '' && $view_user instanceof WP_User ) {
    $verification_email = $view_user->user_email;
}

if ( $status_message === '' ) {
    switch ( $email_status ) {
        case 'verified':
            $status_tone = 'success';
            $status_message = 'Your email address has been verified successfully.';
            break;
        case 'resent':
            $status_tone = 'success';
            $status_message = 'A fresh verification email is on its way.';
            break;
        case 'check-inbox':
            $status_tone = 'info';
            $status_message = 'Check your inbox and click the verification link to unlock your account.';
            break;
        case 'already-verified':
            $status_tone = 'success';
            $status_message = 'This account is already verified.';
            break;
        case 'expired':
            $status_tone = 'error';
            $status_message = 'That verification link has expired. Request a fresh one below.';
            break;
            case 'rate-limited':
            $status_tone = 'error';
            $status_message = 'Too many resend attempts. Please wait a little before trying again.';
            break;
        case 'invalid':
            $status_tone = 'error';
            $status_message = 'That verification link is invalid.';
            break;
        case 'send-error':
            $status_tone = 'error';
            $status_message = 'We could not send a verification email yet. Check your mail transport and try again.';
            break;
        default:
            $status_tone = 'info';
            $status_message = 'Log in to request a verification email for your account.';
            break;
    }
}

$can_resend = $current_user instanceof WP_User && $current_user->exists() && gb_user_requires_email_verification( $current_user );
$contact_url = function_exists( 'gb_get_contact_page_url' ) ? gb_get_contact_page_url() : home_url( '/contact/' );

get_header();
?>

<style>
    .gb-verify-page { background: linear-gradient(180deg, #f8fafc 0%, #eef6f1 100%); min-height: 70vh; padding: 90px 20px; }
    .gb-verify-shell { max-width: 560px; margin: 0 auto; }
    .gb-verify-card { background: #fff; border: 1px solid #dbe4ea; border-radius: 22px; padding: 34px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08); }
    .gb-verify-kicker { display: inline-block; margin-bottom: 14px; font-size: 11px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: #2e7d32; }
    .gb-verify-title { margin: 0 0 10px; font-size: 34px; line-height: 1.05; letter-spacing: -0.04em; color: #0f172a; }
    .gb-verify-copy { margin: 0; color: #475569; line-height: 1.7; }
    .gb-verify-banner { margin: 24px 0; padding: 16px 18px; border-radius: 14px; border: 1px solid #cbd5e1; font-size: 14px; line-height: 1.6; }
    .gb-verify-banner.info { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .gb-verify-banner.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .gb-verify-banner.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .gb-verify-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; margin-bottom: 22px; }
    .gb-verify-label { display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
    .gb-verify-email { font-size: 18px; font-weight: 700; color: #0f172a; word-break: break-word; }
    .gb-verify-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
    .gb-verify-btn { display: inline-flex; align-items: center; justify-content: center; padding: 13px 18px; border-radius: 12px; font-weight: 700; text-decoration: none; border: 1px solid #d1d5db; background: #fff; color: #0f172a; cursor: pointer; }
    .gb-verify-btn-primary { background: #14532d; border-color: #14532d; color: #fff; }
    .gb-verify-footnote { margin: 18px 0 0; font-size: 13px; color: #64748b; line-height: 1.6; }

    @media (max-width: 640px) {
        .gb-verify-page { padding: 60px 16px; }
        .gb-verify-card { padding: 24px; border-radius: 18px; }
        .gb-verify-title { font-size: 28px; }
        .gb-verify-actions { flex-direction: column; }
        .gb-verify-btn { width: 100%; }
    }
</style>

<section class="gb-verify-page">
    <div class="gb-verify-shell">
        <div class="gb-verify-card">
            <span class="gb-verify-kicker">Account Security</span>
            <h1 class="gb-verify-title">Verify your email</h1>
            <p class="gb-verify-copy">The Green Broom uses email verification to protect cleaner and customer accounts before dashboard access, password resets, and booking management continue.</p>

            <div class="gb-verify-banner <?php echo esc_attr( $status_tone ); ?>">
                <?php echo esc_html( $status_message ); ?>
            </div>

            <?php if ( $verification_email !== '' ) : ?>
                <div class="gb-verify-panel">
                    <span class="gb-verify-label">Verification Address</span>
                    <div class="gb-verify-email"><?php echo esc_html( $verification_email ); ?></div>
                </div>
            <?php endif; ?>

            <?php if ( $can_resend ) : ?>
                <form method="post" class="gb-verify-actions">
                    <?php wp_nonce_field( 'gb_resend_email_verification', 'gb_verify_email_nonce' ); ?>
                    <button type="submit" name="gb_resend_email_verification" value="1" class="gb-verify-btn gb-verify-btn-primary">Resend verification email</button>
                    <a href="<?php echo esc_url( wp_logout_url( home_url( '/login' ) ) ); ?>" class="gb-verify-btn">Log out</a>
                </form>
                <p class="gb-verify-footnote">
                    No message yet? Check spam, promotions, or corporate filters. Resend is rate-limited to avoid abuse.
                </p>
                <?php
                /*
                 * A resend button is no use to somebody whose last send failed
                 * outright - pressing it again is the one thing they have
                 * already tried. Give them a way through that does not depend
                 * on email working at all, because a signup that dead-ends is
                 * a lost cleaner or a lost customer.
                 */
                $gb_verify_failed = is_user_logged_in()
                    ? (string) get_user_meta( get_current_user_id(), 'gb_email_verification_last_error', true )
                    : '';
                ?>
                <?php if ( $gb_verify_failed !== '' ) : ?>
                    <p class="gb-verify-footnote" style="margin-top:14px;">
                        Still nothing after a resend? That is our end, not yours.
                        <a href="<?php echo esc_url( $contact_url ); ?>">Tell us</a> and we will confirm your account by
                        hand so you can get on with it.
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <div class="gb-verify-actions">
                    <a href="<?php echo esc_url( $continue_url ); ?>" class="gb-verify-btn gb-verify-btn-primary"><?php echo esc_html( $continue_label ); ?></a>
                    <a href="<?php echo esc_url( $contact_url ); ?>" class="gb-verify-btn">Contact support</a>
                </div>
                <?php if ( ! is_user_logged_in() ) : ?>
                    <p class="gb-verify-footnote">If you opened the link on another device, sign in with the same email address and you will continue into your dashboard.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
