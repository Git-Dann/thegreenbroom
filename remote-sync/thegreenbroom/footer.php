</div><footer id="colophon" class="site-footer">
    </footer>
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

<style>
    #gb-site-footer {
        background-color: #1b5e20; /* Dark Eco Green */
        color: white;
        padding: 60px 0 0;
        font-family: sans-serif;
        margin-top: auto;
    }

    .gb-footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1.5fr; /* 5 Columns for Brand, Company, Support, Legal, CTA */
        gap: 30px;
    }

    /* Column 1: Brand */
    .gb-logo-box {
        background: white; 
        padding: 12px 20px;
        border-radius: 8px;
        display: inline-block;
        margin-bottom: 20px;
    }
    .gb-footer-logo {
        height: 40px;
        width: auto;
        display: block;
    }
    .gb-footer-brand p {
        color: rgba(255,255,255,0.7);
        line-height: 1.6;
        font-size: 0.95rem;
        max-width: 280px;
        margin-top: 0;
    }

    /* Column Headings */
    .gb-footer-col h3 {
        color: #a5d6a7; /* Light Green Accent */
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0 0 20px;
        font-weight: 700;
    }
    .gb-footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .gb-footer-links li { margin-bottom: 12px; }
    .gb-footer-links a {
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s;
    }
    .gb-footer-links a:hover { color: #fff; text-decoration: underline; }

    /* Column 5: CTA */
    .gb-footer-btn {
        background: #2e7d32;
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-block;
        font-weight: 600;
        border: 1px solid rgba(255,255,255,0.2);
        margin-top: 10px;
        font-size: 0.9rem;
    }
    .gb-footer-btn:hover { background: #fff; color: #1b5e20; }

    /* Bottom Bar */
    /* Social icons */
    .gb-social-row { display: flex; gap: 12px; margin-top: 20px; }
    .gb-social-row a { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.12); transition: background 0.2s; }
    .gb-social-row a:hover { background: rgba(255,255,255,0.28); }
    .gb-social-row svg { width: 18px; height: 18px; fill: #fff; }

    .gb-copyright-bar {
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: 50px;
        padding: 25px 0;
        text-align: center;
        color: rgba(255,255,255,0.5);
        font-size: 0.8rem;
    }

    /* Mobile Responsive */
    @media (max-width: 1000px) {
        .gb-footer-container { grid-template-columns: 1fr 1fr; gap: 40px; }
    }
    @media (max-width: 600px) {
        .gb-footer-container { grid-template-columns: 1fr; }
    }
</style>

<footer id="gb-site-footer">
    
    <div class="gb-footer-container">
        
        <div class="gb-footer-brand">
            <div class="gb-logo-box">
                <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png" alt="The Green Broom" class="gb-footer-logo">
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
            <h3>Company</h3>
            <ul class="gb-footer-links">
                <li><a href="/how-it-works">How It Works</a></li>
                <li><a href="/what-we-do">What We Do</a></li>
                <li><a href="/how-we-do-it">How We Do It</a></li>
                <li><a href="/cleaner-listings">Find a Cleaner</a></li>
                <li><a href="<?php echo esc_url($gb_blog_url); ?>">Eco Blog</a></li>
            </ul>
        </div>

        <div class="gb-footer-col">
            <h3>Support</h3>
            <ul class="gb-footer-links">
                <li><a href="<?php echo esc_url($gb_contact_url); ?>">Contact Us</a></li>
                <li><a href="/login">Log In</a></li>
                <li><a href="/register">Become a Cleaner</a></li>
            </ul>
        </div>

        <div class="gb-footer-col">
            <h3>Legal</h3>
            <ul class="gb-footer-links">
                <li><a href="<?php echo esc_url($gb_privacy_url); ?>">Privacy Policy</a></li>
                <li><a href="<?php echo esc_url($gb_terms_url); ?>">Terms & Conditions</a></li>
                <li><a href="<?php echo esc_url(home_url('/cookies/')); ?>">Cookie Policy</a></li>
                <li><a href="<?php echo esc_url(home_url('/gdpr/')); ?>">GDPR</a></li>
            </ul>
        </div>

        <div class="gb-footer-col">
            <h3>Ready to start?</h3>
            <p style="color:rgba(255,255,255,0.7); font-size:0.85rem; margin-bottom: 15px;">Join hundreds of cleaners earning more today.</p>
            <a href="/join" class="gb-footer-btn">Join The Green Broom &rarr;</a>
        </div>

    </div>

    <div class="gb-copyright-bar">
        &copy; <?php echo date('Y'); ?> The Green Broom Ltd. All rights reserved. Built eco-consciously in Manchester.
    </div>

</footer>

<?php wp_footer(); ?>
</body>
</html>
