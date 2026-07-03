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
                <li><a href="<?php echo esc_url($gb_privacy_url . '#cookies-policy'); ?>">Cookie Policy</a></li>
                <li><a href="<?php echo esc_url($gb_privacy_url . '#gdpr-rights'); ?>">GDPR</a></li>
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
