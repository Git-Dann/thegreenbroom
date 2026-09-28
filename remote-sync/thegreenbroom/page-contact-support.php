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
    
    /* box-sizing was missing, so width:100% plus 100px of horizontal padding
       pushed the card past the viewport on every phone. */
    .gb-support-card { background: white; padding: 50px; border-radius: 12px; max-width: 500px; width: 100%; box-sizing: border-box; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    @media (max-width: 600px) { .gb-support-card { padding: 28px 20px; } }
    
    /* Back Link Style */
    .gb-back-link { display: inline-flex; align-items: center; color: #666; text-decoration: none; font-weight: 600; margin-bottom: 20px; font-size: 0.9rem; align-self: flex-start; max-width: 500px; width: 100%; margin: 0 auto 20px; }
    .gb-back-link:hover { color: #2e7d32; }
</style>

<?php
/*
 * Same as the public contact page: the wording lived in the template and so
 * could not be changed from the dashboard. Defaults are the previous text.
 */
$s_back    = gb_page_text('_gb_csup_back',    'Back to Dashboard');
$s_title   = gb_page_text('_gb_csup_title',   'Contact Support');
$s_sub     = gb_page_text('_gb_csup_sub',     'Having trouble? Our team in Liverpool is here to help.');
$s_phone_l = gb_page_text('_gb_csup_phone_label', 'Phone:');
$s_hours   = gb_page_text('_gb_csup_hours',   'Monday to Friday, 9 am to 5 pm');
$s_ok_t    = gb_page_text('_gb_csup_success_title', 'Message sent!');
$s_ok_b    = gb_page_text('_gb_csup_success_body',  "We'll be in touch shortly.");
$s_err_t   = gb_page_text('_gb_csup_error_title',   'Please check your details.');
$s_err_b   = gb_page_text('_gb_csup_error_body',    'All fields are required, including a valid email.');
$s_btn     = gb_page_text('_gb_csup_btn',     'Send Message');
?>

<div id="gb-page-wrapper">
    
    <a href="/cleaner-dashboard" class="gb-back-link">
        <span style="margin-right:8px;">←</span> <?php echo esc_html($s_back); ?>
    </a>

    <div class="gb-support-card">
        <h1 style="margin-top:0; color:#1b5e20;"><?php echo esc_html($s_title); ?></h1>
        <p style="color:#666; margin-bottom:12px;"><?php echo esc_html($s_sub); ?></p>
        <p style="color:#2e7d32; font-weight:700; margin:0 0 4px;"><?php echo esc_html($s_phone_l); ?> <?php echo $support_phone ? '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)) . '">' . esc_html($support_phone) . '</a>' : '07974 903568'; ?></p>
        <p style="color:#64748b; font-size:13px; margin:0 0 30px;"><?php echo esc_html($s_hours); ?></p>

        <?php if($sent): ?>
            <div style="background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:6px; text-align:center;">
                <strong><?php echo esc_html($s_ok_t); ?></strong><br><?php echo esc_html($s_ok_b); ?>
            </div>
        <?php elseif($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:6px; text-align:center;">
                <strong><?php echo esc_html($s_err_t); ?></strong><br><?php echo esc_html($s_err_b); ?>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="gb_contact_action" value="submit_helpdesk">
                <?php wp_nonce_field('gb_contact_submit', 'gb_contact_nonce'); ?>
                <input type="text" name="gb_contact_website" value="" autocomplete="off" tabindex="-1" style="position:absolute; left:-9999px; opacity:0;" aria-hidden="true">
                <input type="hidden" name="gb_contact_ts" value="<?php echo esc_attr( gb_form_timing_token() ); ?>">

                <label style="display:block; font-weight:700; margin-bottom:5px;">Name</label>
                <input type="text" name="c_name" value="<?php echo esc_attr($user->exists() ? $user->display_name : ''); ?>" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px; box-sizing:border-box;">

                <label style="display:block; font-weight:700; margin-bottom:5px;">Email</label>
                <input type="email" name="c_email" value="<?php echo esc_attr($user->exists() ? $user->user_email : ''); ?>" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px; box-sizing:border-box;">

                <label style="display:block; font-weight:700; margin-bottom:5px;">Subject</label>
                <input type="text" name="c_subject" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:15px; box-sizing:border-box;">
                
                <label style="display:block; font-weight:700; margin-bottom:5px;">Message</label>
                <textarea name="c_message" rows="5" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; margin-bottom:20px; box-sizing:border-box;"></textarea>
                
                <button type="submit" style="background:#2e7d32; color:white; padding:15px; width:100%; border:none; border-radius:6px; font-weight:bold; cursor:pointer;"><?php echo esc_html($s_btn); ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
