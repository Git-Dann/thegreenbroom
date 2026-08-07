<?php
/**
 * Template Name: Contact Us
 */
get_header(); 

$user = wp_get_current_user();
$is_logged_in = $user->exists();
$success = isset($_GET['contact_success']);
$error = isset($_GET['contact_error']);
$support_phone = function_exists('gb_get_support_phone') ? gb_get_support_phone() : '';
?>

<style>
    .gb-contact-hero { background: #f8fafc; padding: 80px 0; text-align: center; }
    .gb-contact-container { max-width: 600px; margin: -60px auto 100px; background: white; padding: 50px; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 20px 40px rgba(15,23,42,0.05); position: relative; z-index: 10; }
    .gb-form-group { margin-bottom: 20px; }
    .gb-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; }
    .gb-input { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 15px; box-sizing: border-box; }
    .gb-submit { width: 100%; background: #2e7d32; color: white; border: none; padding: 18px; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 16px; transition: background 0.2s; }
    .gb-submit:hover { background: #1b5e20; }
    .gb-success-msg { background: #dcfce7; color: #166534; padding: 20px; border-radius: 12px; text-align: center; font-weight: 700; margin-bottom: 30px; border: 1px solid #166534; }
</style>

<div class="gb-contact-hero">
    <div class="gb-container">
        <h1 style="font-size: 42px; font-weight: 800; color: #0f172a; margin: 0;">Contact Support</h1>
        <p style="color: #64748b; font-size: 18px; margin-top: 10px;">Send a message directly to our management team.</p>
        <p style="color:#2e7d32; font-weight:700; margin:16px 0 0;">Phone support: <?php echo $support_phone ? '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)) . '">' . esc_html($support_phone) . '</a>' : '07974 903568'; ?></p>
        <p style="color:#64748b; font-size:14px; margin:6px 0 0;">Monday to Friday, 9 am to 5 pm</p>
    </div>
</div>

<div class="gb-contact-container">
    <?php if($success): ?>
        <div class="gb-success-msg">✅ Success! Your message has been sent to our team.</div>
    <?php elseif($error): ?>
        <div class="gb-success-msg" style="background:#fee2e2; color:#991b1b; border-color:#991b1b;">Please complete all fields with a valid email address.</div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="gb_contact_action" value="submit_helpdesk">
        <?php wp_nonce_field('gb_contact_submit', 'gb_contact_nonce'); ?>
        <input type="text" name="gb_contact_website" value="" autocomplete="off" tabindex="-1" style="position:absolute; left:-9999px; opacity:0;" aria-hidden="true">
        
        <div class="gb-form-group">
            <label class="gb-label">Your Name</label>
            <input type="text" name="c_name" value="<?php echo esc_attr($is_logged_in ? $user->display_name : ''); ?>" required class="gb-input">
        </div>

        <div class="gb-form-group">
            <label class="gb-label">Email Address</label>
            <input type="email" name="c_email" value="<?php echo esc_attr($is_logged_in ? $user->user_email : ''); ?>" required class="gb-input">
        </div>

        <div class="gb-form-group">
            <label class="gb-label">Subject</label>
            <input type="text" name="c_subject" required class="gb-input" placeholder="How can we help?">
        </div>

        <div class="gb-form-group">
            <label class="gb-label">Message</label>
            <textarea name="c_message" rows="6" required class="gb-input" style="height:auto;"></textarea>
        </div>

        <button type="submit" class="gb-submit">Send Message</button>
    </form>
</div>

<?php get_footer(); ?>
