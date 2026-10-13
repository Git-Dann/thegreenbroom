</div>
<?php
/**
 * The template for displaying the footer
 * Integrated with header.php core pages and new Legal column
 */

$gb_contact_url = function_exists('gb_get_contact_page_url') ? gb_get_contact_page_url() : home_url('/contact/');
$gb_privacy_url = function_exists('gb_get_privacy_page_url') ? gb_get_privacy_page_url() : home_url('/privacy/');
$gb_terms_url = function_exists('gb_get_terms_page_url') ? gb_get_terms_page_url() : home_url('/terms/');
$gb_blog_url = function_exists('gb_get_blog_url') ? gb_get_blog_url() : home_url('/blog/');

// Social links — only rendered when a URL has been set in the dashboard.
$gb_settings_id = (int) get_option('page_on_front');
$gb_social_defs = [
    'facebook'  => ['label' => 'Facebook',  'svg' => '<path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/>'],
    'instagram' => ['label' => 'Instagram', 'svg' => '<path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.3 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.3-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.7.1-1.1.1-1.7.2-2.1.4-.5.2-.9.4-1.3.8-.4.4-.6.8-.8 1.3-.2.4-.3 1-.4 2.1-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1 1.1.2 1.7.4 2.1.2.5.4.9.8 1.3.4.4.8.6 1.3.8.4.2 1 .3 2.1.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.2 2.1-.4.5-.2.9-.4 1.3-.8.4-.4.6-.8.8-1.3.2-.4.3-1 .4-2.1.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-1.1-.2-1.7-.4-2.1-.2-.5-.4-.9-.8-1.3-.4-.4-.8-.6-1.3-.8-.4-.2-1-.3-2.1-.4-1.2-.1-1.6-.1-4.7-.1zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 8.1a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4zm6.3-8.3a1.15 1.15 0 1 1-2.3 0 1.15 1.15 0 0 1 2.3 0z"/>'],
    'twitter'   => ['label' => 'X',         'svg' => '<path d="M18.2 2.3h3.3l-7.2 8.2 8.5 11.2h-6.6l-5.2-6.8-6 6.8H1.7l7.7-8.8L1.2 2.3h6.8l4.7 6.2 5.5-6.2zm-1.2 17.5h1.8L7.1 4.1H5.2l11.8 15.7z"/>'],
    'linkedin'  => ['label' => 'LinkedIn',  'svg' => '<path d="M20.4 3H3.6C3 3 2.5 3.5 2.5 4.1v15.8c0 .6.5 1.1 1.1 1.1h16.8c.6 0 1.1-.5 1.1-1.1V4.1c0-.6-.5-1.1-1.1-1.1zM8.3 18.3H5.6V9.7h2.7v8.6zM7 8.5a1.6 1.6 0 1 1 0-3.2 1.6 1.6 0 0 1 0 3.2zm11.3 9.8h-2.7v-4.2c0-1 0-2.3-1.4-2.3s-1.6 1.1-1.6 2.2v4.3H9.9V9.7h2.6v1.2h.1c.4-.7 1.2-1.4 2.5-1.4 2.7 0 3.2 1.8 3.2 4.1v4.7z"/>'],
    'tiktok'    => ['label' => 'TikTok',    'svg' => '<path d="M16.6 5.8c-1-.7-1.7-1.7-1.9-3H12v11.3c0 1.3-1.1 2.4-2.4 2.4a2.4 2.4 0 0 1 0-4.8c.3 0 .5 0 .7.1V9.2a5.3 5.3 0 0 0-.7 0 5.2 5.2 0 1 0 5.2 5.2V8.7c1 .7 2.2 1.1 3.5 1.1V7.1c-.6 0-1.2-.2-1.7-.5l-.1-.8z"/>'],
    'youtube'   => ['label' => 'YouTube',   'svg' => '<path d="M23 8.2s-.2-1.6-.9-2.3c-.9-.9-1.8-.9-2.3-1C16.6 4.7 12 4.7 12 4.7h0s-4.6 0-7.8.2c-.5.1-1.4.1-2.3 1C1.2 6.6 1 8.2 1 8.2S.8 10 .8 11.9v1.7C.8 15.4 1 17.2 1 17.2s.2 1.6.9 2.3c.9.9 2 .9 2.5 1 1.8.2 7.6.2 7.6.2s4.6 0 7.8-.2c.5-.1 1.4-.1 2.3-1 .7-.7.9-2.3.9-2.3s.2-1.8.2-3.6v-1.7c0-1.9-.2-3.7-.2-3.7zM9.7 15.3V9.5l6 2.9-6 2.9z"/>'],
];
$gb_social_links = [];
foreach ($gb_social_defs as $gb_key => $gb_def) {
    $gb_url = get_post_meta($gb_settings_id, '_gb_social_' . $gb_key, true);
    if (!empty($gb_url)) {
        $gb_social_links[$gb_key] = ['url' => $gb_url] + $gb_def;
    }
}
?>


<footer id="gb-site-footer">
    
    <div class="gb-footer-container">
        
        <div class="gb-footer-brand">
            <div class="gb-logo-box">
                <picture>
                    <?php gb_picture_sources( 'https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png' ); ?>
                <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png" width="974" height="139" alt="The Green Broom" class="gb-footer-logo" loading="lazy" decoding="async">
                </picture>
            </div>
            <p>Connecting homeowners with trusted, eco-friendly cleaning professionals. Simple, safe, and sustainable.</p>
            <?php if (!empty($gb_social_links)): ?>
            <div class="gb-social-row">
                <?php foreach ($gb_social_links as $gb_key => $gb_s): ?>
                    <a href="<?php echo esc_url($gb_s['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($gb_s['label']); ?>" title="<?php echo esc_attr($gb_s['label']); ?>">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><?php echo $gb_s['svg']; ?></svg>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="gb-footer-col">
            <h2>Company</h2>
            <ul class="gb-footer-links">
                <li><a href="/what-cleaners-do">What Cleaners Do</a></li>
                <li><a href="/the-green-broom-standard">The Green Broom Standard</a></li>
                <li><a href="/eco-products">Eco Products</a></li>
                <li><a href="/cleaner-listings">Find a Cleaner</a></li>
                <li><a href="<?php echo esc_url($gb_blog_url); ?>">Eco Blog</a></li>
            </ul>
        </div>

        <div class="gb-footer-col">
            <h2>Support</h2>
            <ul class="gb-footer-links">
                <li><a href="<?php echo esc_url($gb_contact_url); ?>">Contact Us</a></li>
                <li><a href="/login">Log In</a></li>
                <li><a href="/register">Become a Cleaner</a></li>
            </ul>
        </div>

        <div class="gb-footer-col">
            <h2>Legal</h2>
            <ul class="gb-footer-links">
                <li><a href="<?php echo esc_url($gb_privacy_url); ?>">Privacy Policy</a></li>
                <li><a href="<?php echo esc_url($gb_terms_url); ?>">Terms & Conditions</a></li>
                <?php // Linked from the sign-up form's agreement box, so it has to be findable before anyone subscribes. ?>
                <li><a href="<?php echo esc_url(function_exists('gb_get_subscriber_terms_page_url') ? gb_get_subscriber_terms_page_url() : home_url('/subscribers-terms-of-association/')); ?>">Subscribers' Terms</a></li>
                <li><a href="<?php echo esc_url(function_exists('gb_get_cookie_policy_page_url') ? gb_get_cookie_policy_page_url() : home_url('/cookies/')); ?>">Cookie Policy</a></li>
                <li><a href="<?php echo esc_url(home_url('/gdpr/')); ?>">GDPR</a></li>
                <?php /* Withdrawing consent has to be as easy as giving it, so the bar
                         is always one click away from every page. Only shown when
                         the bar itself is switched on in the manager dashboard. */ ?>
                <?php $gb_cookie_bar = function_exists('gb_cookie_consent_settings') ? gb_cookie_consent_settings() : array('enabled' => false); ?>
                <?php if (!empty($gb_cookie_bar['enabled'])): ?>
                    <li><a href="#" data-gb-cookie-settings>Cookie settings</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="gb-footer-col">
            <h2>Ready to start?</h2>
            <p style="color:rgba(255,255,255,0.7); font-size:0.85rem; margin-bottom: 15px;">Join hundreds of cleaners earning more today.</p>
            <a href="/join" class="gb-footer-btn">Join The Green Broom &rarr;</a>
        </div>

    </div>

    <div class="gb-copyright-bar">
        &copy; <?php echo esc_html( wp_date('Y') ); ?> The Green Broom Ltd. All rights reserved. Built eco-consciously in Liverpool.
    </div>

</footer>

</div>

<?php wp_footer(); ?>
</body>
</html>
