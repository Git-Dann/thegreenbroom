<?php
/* Template Name: Contact Support */
get_header(); 
$user = wp_get_current_user();
$sent = isset($_GET['contact_success']);
$error = isset($_GET['contact_error']);
$support_phone = function_exists('gb_get_support_phone') ? gb_get_support_phone() : '';
?>

<style>
    #gb-page-wrapper { width: 100vw; position: relative; left: 50%; margin-left: -50vw; background: #f4f6f8; min-height: 100vh; font-family: sans-serif; display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px 20px; box-sizing: border-box; }
    
    .gb-support-card { background: white; padding: 50px; border-radius: 12px; max-width: 500px; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    
    /* Back Link Style */
    .gb-back-link { display: inline-flex; align-items: center; color: #666; text-decoration: none; font-weight: 600; margin-bottom: 20px; font-size: 0.9rem; align-self: flex-start; max-width: 500px; width: 100%; margin: 0 auto 20px; }
    .gb-back-link:hover { color: #2e7d32; }
</style>

<div id="gb-page-wrapper">
    
    <a href="/cleaner-dashboard" class="gb-back-link">
        <span style="margin-right:8px;">←</span> Back to Dashboard
    </a>

    <div class="gb-support-card">
        <h1 style="margin-top:0; color:#1b5e20;">Contact Support</h1>
        <p style="color:#666; margin-bottom:12px;">Having trouble? Our team in Liverpool is here to help.</p>
        <p style="color:#2e7d32; font-weight:700; margin:0 0 4px;">Phone support: <?php echo $support_phone ? '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)) . '">' . esc_html($support_phone) . '</a>' : '07974 903568'; ?></p>
        <p style="color:#64748b; font-size:13px; margin:0 0 30px;">Monday to Friday, 9 am to 5 pm</p>

        <?php if($sent): ?>
            <div style="background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:6px; text-align:center;">
                <strong>Message sent!</strong><br>We'll be in touch shortly.
            </div>
        <?php elseif($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:6px; text-align:center;">
                <strong>Please check your details.</strong><br>All fields are required, including a valid email.
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="gb_contact_action" value="submit_helpdesk">
                <?php wp_nonce_field('gb_contact_submit', 'gb_contact_nonce'); ?>
                <input type="text" name="gb_contact_website" value="" autocomplete="off" tabindex="-1" style="position:absolute; left:-9999px; opacity:0;" aria-hidden="true">

                <label style="display:block; font-weight:700; margin-bottom:5px;">Name</label>
                <input type="text" name="c_name" value="<?php echo esc_attr($user->exists() ? $user->display_name : ''); ?>" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px; box-sizing:border-box;">

                <label style="display:block; font-weight:700; margin-bottom:5px;">Email</label>
                <input type="email" name="c_email" value="<?php echo esc_attr($user->exists() ? $user->user_email : ''); ?>" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px; box-sizing:border-box;">

                <label style="display:block; font-weight:700; margin-bottom:5px;">Subject</label>
                <input type="text" name="c_subject" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px; box-sizing:border-box;">
                
                <label style="display:block; font-weight:700; margin-bottom:5px;">Message</label>
                <textarea name="c_message" rows="5" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:20px; box-sizing:border-box;"></textarea>
                
                <button type="submit" style="background:#2e7d32; color:white; padding:15px; width:100%; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Send Message</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
