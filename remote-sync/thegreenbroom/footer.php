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
/*
 * Official brand icons from Font Awesome Free 7.3.1 (fontawesome.com),
 * Icons: CC BY 4.0. Each keeps its own viewBox. Never redraw these by hand:
 * swap in the next release's SVG instead.
 */
$gb_social_defs = [
    'facebook'  => ['label' => 'Facebook', 'viewbox' => '0 0 512 512', 'svg' => '<path d="M512 256C512 114.6 397.4 0 256 0S0 114.6 0 256C0 376 82.7 476.8 194.2 504.5l0-170.3-52.8 0 0-78.2 52.8 0 0-33.7c0-87.1 39.4-127.5 125-127.5 16.2 0 44.2 3.2 55.7 6.4l0 70.8c-6-.6-16.5-1-29.6-1-42 0-58.2 15.9-58.2 57.2l0 27.8 83.6 0-14.4 78.2-69.3 0 0 175.9C413.8 494.8 512 386.9 512 256z"/>'],
    'instagram' => ['label' => 'Instagram', 'viewbox' => '0 0 448 512', 'svg' => '<path d="M224.3 141a115 115 0 1 0 -.6 230 115 115 0 1 0 .6-230zm-.6 40.4a74.6 74.6 0 1 1 .6 149.2 74.6 74.6 0 1 1 -.6-149.2zm93.4-45.1a26.8 26.8 0 1 1 53.6 0 26.8 26.8 0 1 1 -53.6 0zm129.7 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM399 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/>'],
    'twitter' => ['label' => 'X', 'viewbox' => '0 0 448 512', 'svg' => '<path d="M357.2 48L427.8 48 273.6 224.2 455 464 313 464 201.7 318.6 74.5 464 3.8 464 168.7 275.5-5.2 48 140.4 48 240.9 180.9 357.2 48zM332.4 421.8l39.1 0-252.4-333.8-42 0 255.3 333.8z"/>'],
    'linkedin' => ['label' => 'LinkedIn', 'viewbox' => '0 0 448 512', 'svg' => '<path d="M416 32L31.9 32C14.3 32 0 46.5 0 64.3L0 447.7C0 465.5 14.3 480 31.9 480L416 480c17.6 0 32-14.5 32-32.3l0-383.4C448 46.5 433.6 32 416 32zM135.4 416l-66.4 0 0-213.8 66.5 0 0 213.8-.1 0zM102.2 96a38.5 38.5 0 1 1 0 77 38.5 38.5 0 1 1 0-77zM384.3 416l-66.4 0 0-104c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9l0 105.8-66.4 0 0-213.8 63.7 0 0 29.2 .9 0c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9l0 117.2z"/>'],
    'tiktok' => ['label' => 'TikTok', 'viewbox' => '0 0 448 512', 'svg' => '<path d="M448.5 209.9c-44 .1-87-13.6-122.8-39.2l0 178.7c0 33.1-10.1 65.4-29 92.6s-45.6 48-76.6 59.6-64.8 13.5-96.9 5.3-60.9-25.9-82.7-50.8-35.3-56-39-88.9 2.9-66.1 18.6-95.2 40-52.7 69.6-67.7 62.9-20.5 95.7-16l0 89.9c-15-4.7-31.1-4.6-46 .4s-27.9 14.6-37 27.3-14 28.1-13.9 43.9 5.2 31 14.5 43.7 22.4 22.1 37.4 26.9 31.1 4.8 46-.1 28-14.4 37.2-27.1 14.2-28.1 14.2-43.8l0-349.4 88 0c-.1 7.4 .6 14.9 1.9 22.2 3.1 16.3 9.4 31.9 18.7 45.7s21.3 25.6 35.2 34.6c19.9 13.1 43.2 20.1 67 20.1l0 87.4z"/>'],
    'youtube' => ['label' => 'YouTube', 'viewbox' => '0 0 576 512', 'svg' => '<path d="M549.7 124.1C543.5 100.4 524.9 81.8 501.4 75.5 458.9 64 288.1 64 288.1 64S117.3 64 74.7 75.5C51.2 81.8 32.7 100.4 26.4 124.1 15 167 15 256.4 15 256.4s0 89.4 11.4 132.3c6.3 23.6 24.8 41.5 48.3 47.8 42.6 11.5 213.4 11.5 213.4 11.5s170.8 0 213.4-11.5c23.5-6.3 42-24.2 48.3-47.8 11.4-42.9 11.4-132.3 11.4-132.3s0-89.4-11.4-132.3zM232.2 337.6l0-162.4 142.7 81.2-142.7 81.2z"/>'],
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
                        <svg viewBox="<?php echo esc_attr($gb_s['viewbox']); ?>" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><?php echo $gb_s['svg']; ?></svg>
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
