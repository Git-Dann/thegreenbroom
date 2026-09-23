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

/*
 * Every visible word on this page used to be typed into the template, so it
 * was the one public page a manager could not change. Each string now reads
 * from page meta and falls back to exactly what was here before, so nothing
 * moves until somebody edits it.
 */
$gb_t = function ($key, $default) {
    $value = trim((string) get_post_meta(get_the_ID(), $key, true));
    return $value === '' ? $default : $value;
};
$c_title   = $gb_t('_gb_contact_title',   'Contact Support');
$c_sub     = $gb_t('_gb_contact_sub',     'Send a message directly to our management team.');
$c_phone_l = $gb_t('_gb_contact_phone_label', 'Phone support:');
$c_hours   = $gb_t('_gb_contact_hours',   'Monday to Friday, 9 am to 5 pm');
$c_ok      = $gb_t('_gb_contact_success', 'Success! Your message has been sent to our team.');
$c_err     = $gb_t('_gb_contact_error',   'Please complete all fields with a valid email address.');
$c_l_name  = $gb_t('_gb_contact_label_name',    'Your Name');
$c_l_email = $gb_t('_gb_contact_label_email',   'Email Address');
$c_l_subj  = $gb_t('_gb_contact_label_subject', 'Subject');
$c_ph_subj = $gb_t('_gb_contact_ph_subject',    'How can we help?');
$c_l_msg   = $gb_t('_gb_contact_label_message', 'Message');
$c_btn     = $gb_t('_gb_contact_btn',     'Send Message');
?>

<style>
    .gb-contact-hero { background: #f8fafc; padding: 80px 0; text-align: center; }
    .gb-contact-container { max-width: 600px; margin: -60px auto 100px; background: white; padding: 50px; border-radius: 20px; border: 1px solid #f1f5f9; box-shadow: 0 20px 40px rgba(15,23,42,0.05); position: relative; z-index: 10; }
    .gb-form-group { margin-bottom: 20px; }
    .gb-label { display: block; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px; }
    .gb-input { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 15px; box-sizing: border-box; }
    .gb-submit { width: 100%; background: #2e7d32; color: white; border: none; padding: 18px; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 16px; transition: background 0.2s; }
    .gb-submit:hover { background: #1b5e20; }
    .gb-success-msg { background: #dcfce7; color: #166534; padding: 20px; border-radius: 12px; text-align: center; font-weight: 700; margin-bottom: 30px; border: 1px solid #166534; }
</style>

<main id="main-content">
    <div class="gb-contact-hero">
        <div class="gb-container">
            <h1 style="font-size: 42px; font-weight: 800; color: #0f172a; margin: 0;"><?php echo esc_html($c_title); ?></h1>
            <p style="color: #64748b; font-size: 18px; margin-top: 10px;"><?php echo esc_html($c_sub); ?></p>
            <p style="color:#2e7d32; font-weight:700; margin:16px 0 0;"><?php echo esc_html($c_phone_l); ?> <?php echo $support_phone ? '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)) . '">' . esc_html($support_phone) . '</a>' : '07974 903568'; ?></p>
            <p style="color:#64748b; font-size:14px; margin:6px 0 0;"><?php echo esc_html($c_hours); ?></p>
        </div>
    </div>

    <div class="gb-contact-container">
        <?php if($success): ?>
            <div class="gb-success-msg" role="status"><?php echo esc_html($c_ok); ?></div>
        <?php elseif($error): ?>
            <div class="gb-success-msg" role="alert" style="background:#fee2e2; color:#991b1b; border-color:#991b1b;"><?php echo esc_html($c_err); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="gb_contact_action" value="submit_helpdesk">
            <?php wp_nonce_field('gb_contact_submit', 'gb_contact_nonce'); ?>
            <input type="text" name="gb_contact_website" value="" autocomplete="off" tabindex="-1" style="position:absolute; left:-9999px; opacity:0;" aria-hidden="true">
            <input type="hidden" name="gb_contact_ts" value="<?php echo esc_attr( gb_form_timing_token() ); ?>">

            <div class="gb-form-group">
                <label class="gb-label" for="contact-name"><?php echo esc_html($c_l_name); ?></label>
                <input id="contact-name" type="text" name="c_name" value="<?php echo esc_attr($is_logged_in ? $user->display_name : ''); ?>" required class="gb-input" autocomplete="name">
            </div>

            <div class="gb-form-group">
                <label class="gb-label" for="contact-email"><?php echo esc_html($c_l_email); ?></label>
                <input id="contact-email" type="email" name="c_email" value="<?php echo esc_attr($is_logged_in ? $user->user_email : ''); ?>" required class="gb-input" autocomplete="email">
            </div>

            <div class="gb-form-group">
                <label class="gb-label" for="contact-subject"><?php echo esc_html($c_l_subj); ?></label>
                <input id="contact-subject" type="text" name="c_subject" required class="gb-input" placeholder="<?php echo esc_attr($c_ph_subj); ?>">
            </div>

            <div class="gb-form-group">
                <label class="gb-label" for="contact-message"><?php echo esc_html($c_l_msg); ?></label>
                <textarea id="contact-message" name="c_message" rows="6" required class="gb-input" style="height:auto;"></textarea>
            </div>

            <button type="submit" class="gb-submit"><?php echo esc_html($c_btn); ?></button>
        </form>
    </div>
</main>

<?php get_footer(); ?>
