<?php
// Settings are globally stored on the Front Page ID for site-wide access
$settings_id = get_option('page_on_front');
$email_verification_setting = get_post_meta($settings_id, '_gb_require_email_verification', true);
$email_verification_setting = $email_verification_setting === '' ? 'yes' : $email_verification_setting;
$stripe_config = function_exists('gb_get_stripe_config') ? gb_get_stripe_config() : [];
$stripe_missing = function_exists('gb_get_stripe_config_issues')
    ? gb_get_stripe_config_issues($stripe_config, ['publishable_key', 'secret_key', 'webhook_secret', 'connect_webhook_secret'])
    : [];
$stripe_notice = isset($_GET['stripe_notice']) ? sanitize_key(wp_unslash($_GET['stripe_notice'])) : '';
$stripe_msg_raw = isset($_GET['stripe_msg']) ? sanitize_text_field(wp_unslash($_GET['stripe_msg'])) : '';
$stripe_msg = $stripe_msg_raw !== '' ? rawurldecode($stripe_msg_raw) : '';
$stripe_mode = isset($stripe_config['mode']) ? $stripe_config['mode'] : 'test';
$stripe_mode_label = $stripe_mode === 'live' ? 'Live Mode' : 'Test Mode';
$brevo_config = function_exists('gb_get_brevo_settings') ? gb_get_brevo_settings() : [];
$brevo_enabled = ! empty($brevo_config['enabled']);
$brevo_key_present = ! empty($brevo_config['api_key']);
$brevo_notice = isset($_GET['brevo_notice']) ? sanitize_key(wp_unslash($_GET['brevo_notice'])) : '';
$brevo_msg_raw = isset($_GET['brevo_msg']) ? sanitize_text_field(wp_unslash($_GET['brevo_msg'])) : '';
$brevo_msg = $brevo_msg_raw !== '' ? rawurldecode($brevo_msg_raw) : '';
$support_email = get_post_meta($settings_id, '_gb_support_email', true) ?: 'info@thegreenbroom.co.uk';
$support_phone = get_post_meta($settings_id, '_gb_support_phone', true) ?: '07974 903568';
$from_address = get_post_meta($settings_id, '_gb_email_from_address', true) ?: 'info@thegreenbroom.co.uk';
$reply_to = get_post_meta($settings_id, '_gb_email_reply_to', true) ?: 'info@thegreenbroom.co.uk';
$active_settings_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'env';
$settings_sections = ['env', 'safeguarding', 'email', 'social', 'agreement', 'monetisation', 'stripe', 'stripe-tools', 'account'];
if (!in_array($active_settings_section, $settings_sections, true)) {
    $active_settings_section = 'env';
}
?>

<style>
    .gb-settings-header { align-items: center; }
    .gb-settings-header__actions { display:flex; align-items:center; gap:12px; }
    .gb-settings-summary { display:flex; flex-wrap:wrap; gap:8px; margin-top:18px; }
    .gb-settings-summary span { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid #e2e8f0; border-radius:999px; background:#fff; color:#475569; font-size:11px; font-weight:700; }
    .gb-settings-summary span::before { content:""; width:7px; height:7px; border-radius:50%; background:#94a3b8; }
    .gb-settings-summary .is-good::before { background:#16a34a; }
    .gb-settings-summary .is-warn::before { background:#d97706; }
    .gb-settings-shell { display:grid; grid-template-columns:220px minmax(0, 1fr); gap:22px; align-items:start; }
    .gb-settings-nav { position:sticky; top:20px; }
    .gb-settings-nav-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow:0 1px 3px rgba(15,23,42,.04); }
    .gb-settings-nav-title { margin:0 8px 10px; color:#0f172a; font-size:13px; font-weight:800; }
    .gb-settings-nav-link { display:flex; align-items:center; gap:10px; width:100%; padding:10px 9px; border:0; border-radius:8px; background:transparent; color:#64748b; font:inherit; font-size:12px; font-weight:700; text-align:left; text-decoration:none; cursor:pointer; }
    .gb-settings-nav-link:hover { background:#f8fafc; color:#1b5e20; }
    .gb-settings-nav-link.is-active { background:#ecfdf3; color:#166534; }
    .gb-settings-nav-link small { display:block; margin-top:2px; color:#94a3b8; font-size:10px; font-weight:500; }
    .gb-settings-nav-link.is-active small { color:#4d7c5a; }
    .gb-settings-nav-icon { width:22px; color:inherit; text-align:center; font-size:14px; }
    .gb-settings-content { min-width:0; }
    .gb-editor-section { background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px; overflow: hidden; }
    html.gb-settings-js .gb-settings-panel:not(.is-active) { display:none; }
    .gb-editor-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
    .gb-editor-header .dashicons { font-size: 16px; color: #64748b; }
    .gb-editor-body { padding: 25px; }
    .gb-form-row { margin-bottom: 20px; }
    .gb-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .gb-input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
    .gb-link-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .gb-btn-small-os { text-decoration: none; padding: 8px 16px; border: 1px solid #e2e8f0; border-radius: 6px; color: #334155; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; transition: all 0.2s ease; background: white; cursor: pointer; }
    .gb-btn-small-os:hover { background:#f8fafc; border-color:#cbd5e1; }
    @media (max-width: 900px) {
        .gb-settings-shell { grid-template-columns:1fr; }
        .gb-settings-nav { position:static; }
        .gb-settings-nav-card { display:flex; gap:8px; overflow-x:auto; padding:8px; }
        .gb-settings-nav-title { display:none; }
        .gb-settings-nav-link { flex:0 0 auto; width:auto; white-space:nowrap; }
        .gb-settings-nav-link small { display:none; }
        .gb-settings-nav-icon { display:none; }
    }
    @media (max-width: 640px) {
        .gb-settings-header { align-items:flex-start; flex-direction:column; gap:16px; }
        .gb-settings-header__actions { width:100%; }
        .gb-settings-header__actions button { width:100%; }
        .gb-editor-body { padding:18px; }
        .gb-link-row { grid-template-columns:1fr; gap:0; }
    }
</style>

<div class="booking-controls gb-settings-header">
    <div>
        <a href="?tab=overview" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Dashboard</a>
        <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a;">Platform Settings</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Control payment environment, safeguarding rules, and platform configuration.</p>
        <div class="gb-settings-summary" aria-label="Settings status">
            <span class="<?php echo $stripe_mode === 'live' ? 'is-warn' : 'is-good'; ?>">Stripe: <?php echo esc_html($stripe_mode_label); ?></span>
            <span class="<?php echo $brevo_enabled && $brevo_key_present ? 'is-good' : 'is-warn'; ?>">Brevo: <?php echo $brevo_enabled && $brevo_key_present ? 'Ready' : 'Needs setup'; ?></span>
            <span class="<?php echo empty($stripe_missing) ? 'is-good' : 'is-warn'; ?>">Stripe keys: <?php echo empty($stripe_missing) ? 'Complete' : 'Action needed'; ?></span>
        </div>
    </div>
    <div class="gb-settings-header__actions">
        <button type="submit" form="gb-settings-form" class="gb-header-btn" style="height:44px; background:#2e7d32; color:white; border:none; padding:0 30px; border-radius:8px; font-weight:700; cursor:pointer;">Update Settings</button>
    </div>
</div>
<div class="gb-builder-layout gb-settings-shell" data-settings-shell>
    <aside class="gb-settings-nav" aria-label="Settings sections">
        <div class="gb-settings-nav-card">
            <p class="gb-settings-nav-title">Settings</p>
            <a href="#env" class="gb-settings-nav-link" data-settings-target="env"><span class="gb-settings-nav-icon">🌐</span><span>Environment<small>Stripe mode</small></span></a>
            <a href="#safeguarding" class="gb-settings-nav-link" data-settings-target="safeguarding"><span class="gb-settings-nav-icon">🛡</span><span>Safeguarding<small>Booking rules</small></span></a>
            <a href="#email" class="gb-settings-nav-link" data-settings-target="email"><span class="gb-settings-nav-icon">✉</span><span>Email &amp; delivery<small>Brevo and sender</small></span></a>
            <a href="#social" class="gb-settings-nav-link" data-settings-target="social"><span class="gb-settings-nav-icon">↗</span><span>Contact &amp; social<small>Public details</small></span></a>
            <a href="#agreement" class="gb-settings-nav-link" data-settings-target="agreement"><span class="gb-settings-nav-icon">▤</span><span>Cleaner agreement<small>Terms and signature</small></span></a>
            <a href="#documents" class="gb-settings-nav-link" data-settings-target="documents"><span class="gb-settings-nav-icon">▦</span><span>Documents<small>Downloadable PDFs</small></span></a>
            <a href="#monetisation" class="gb-settings-nav-link" data-settings-target="monetisation"><span class="gb-settings-nav-icon">£</span><span>Tax &amp; fees<small>VAT settings</small></span></a>
            <a href="#stripe" class="gb-settings-nav-link" data-settings-target="stripe"><span class="gb-settings-nav-icon">🔑</span><span>Stripe credentials<small>Keys and webhooks</small></span></a>
            <a href="#stripe-tools" class="gb-settings-nav-link" data-settings-target="stripe-tools"><span class="gb-settings-nav-icon">🛒</span><span>Stripe tools<small>Storefront preview</small></span></a>
            <a href="#account" class="gb-settings-nav-link" data-settings-target="account"><span class="gb-settings-nav-icon">●</span><span>Your account<small>Password</small></span></a>
        </div>
    </aside>

    <div class="gb-builder-content gb-settings-content">
        <form method="post" id="gb-settings-form">
            <input type="hidden" name="gb_save_action" value="save_settings">
            <input type="hidden" name="post_id" value="<?php echo $settings_id; ?>">
            <input type="hidden" name="_gb_settings_section" value="<?php echo esc_attr($active_settings_section); ?>">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

            <div id="env" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-admin-site"></span> API Environment</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 16px; padding:12px 14px; border:1px solid <?php echo $stripe_mode === 'live' ? '#fde68a' : '#bfdbfe'; ?>; border-radius:8px; background:<?php echo $stripe_mode === 'live' ? '#fffbeb' : '#eff6ff'; ?>; color:<?php echo $stripe_mode === 'live' ? '#92400e' : '#1d4ed8'; ?>; font-size:13px;">
                        Active Stripe environment: <strong><?php echo esc_html($stripe_mode_label); ?></strong>.
                        <?php if ($stripe_mode === 'test'): ?>
                            Connect onboarding links, checkout sessions, and webhook validation will use your Stripe sandbox credentials.
                        <?php else: ?>
                            Live mode uses real Stripe credentials and real money movement.
                        <?php endif; ?>
                    </div>
                    <div class="gb-form-row" style="margin-bottom: 0;">
                        <label class="gb-label">Operation Mode</label>
                        <select name="_gb_stripe_mode" class="gb-input" style="height: 45px;">
                            <option value="test" <?php selected(get_post_meta($settings_id, '_gb_stripe_mode', true), 'test'); ?>>🛠️ Test Mode (Sandbox)</option>
                            <option value="live" <?php selected(get_post_meta($settings_id, '_gb_stripe_mode', true), 'live'); ?>>💰 Live Mode (Real Payments)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="safeguarding" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-shield-alt"></span> Standards & Safeguarding</div>
                <div class="gb-editor-body">
                    <div class="gb-link-row" style="margin-bottom: 20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Min. Booking Lead Time (Hours)</label>
                            <input type="number" name="_gb_lead_time" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_lead_time', true) ?: '24'); ?>">
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Insurance Requirement</label>
                            <select name="_gb_require_insurance" class="gb-input" style="height: 45px;">
                                <option value="yes" <?php selected(get_post_meta($settings_id, '_gb_require_insurance', true), 'yes'); ?>>Mandatory (Proof Required)</option>
                                <option value="no" <?php selected(get_post_meta($settings_id, '_gb_require_insurance', true), 'no'); ?>>Optional (Self-Certified)</option>
                                <option value="provided" <?php selected(get_post_meta($settings_id, '_gb_require_insurance', true), 'provided'); ?>>Insurance Provided by The Green Broom</option>
                            </select>
                        </div>
                    </div>
                    <div class="gb-link-row">
                        <div class="gb-form-row">
                            <label class="gb-label">Cancellation Window (Hours)</label>
                            <input type="number" name="_gb_cancel_window" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_cancel_window', true) ?: '48'); ?>">
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Eco-Standard Agreement</label>
                            <select name="_gb_eco_commitment" class="gb-input" style="height: 45px;">
                                <option value="yes" <?php selected(get_post_meta($settings_id, '_gb_eco_commitment', true), 'yes'); ?>>Enforced at Signup</option>
                                <option value="no" <?php selected(get_post_meta($settings_id, '_gb_eco_commitment', true), 'no'); ?>>Information Only</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="email" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-email-alt"></span> Email & Delivery</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 18px; padding:12px 14px; border:1px solid <?php echo $brevo_enabled && $brevo_key_present ? '#bbf7d0' : '#fde68a'; ?>; border-radius:8px; background:<?php echo $brevo_enabled && $brevo_key_present ? '#f0fdf4' : '#fffbeb'; ?>; color:<?php echo $brevo_enabled && $brevo_key_present ? '#166534' : '#92400e'; ?>; font-size:13px;">
                        <strong>Brevo email delivery:</strong>
                        <?php if ($brevo_enabled && $brevo_key_present): ?>Enabled and ready to test.<?php elseif ($brevo_enabled): ?>Enabled, but the API key is missing.<?php else: ?>Not connected yet. Add the API key and enable Brevo below.<?php endif; ?>
                        Verification, support, booking, password reset, and other site emails will use Brevo when it is enabled.
                    </div>
                    <?php if ($brevo_notice !== '' && $brevo_msg !== ''): ?>
                        <div style="margin:0 0 18px; padding:10px 12px; border:1px solid <?php echo $brevo_notice === 'ok' ? '#dcfce7' : '#fecaca'; ?>; border-radius:8px; background:<?php echo $brevo_notice === 'ok' ? '#f0fdf4' : '#fef2f2'; ?>; color:<?php echo $brevo_notice === 'ok' ? '#166534' : '#991b1b'; ?>; font-size:13px;">
                            <?php echo esc_html($brevo_msg); ?>
                        </div>
                    <?php endif; ?>
                    <div class="gb-link-row" style="margin-bottom:20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Use Brevo for Site Emails</label>
                            <select name="_gb_brevo_enabled" class="gb-input" style="height:45px;">
                                <option value="yes" <?php selected($brevo_enabled, true); ?>>Enabled</option>
                                <option value="no" <?php selected($brevo_enabled, false); ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Brevo API Key</label>
                            <input type="password" name="_gb_brevo_api_key" class="gb-input" value="" autocomplete="new-password" placeholder="<?php echo $brevo_key_present ? 'Saved key present — leave blank to keep it' : 'Paste your Brevo v3 API key'; ?>">
                        </div>
                    </div>
                    <div style="margin:0 0 20px; padding:12px 14px; border:1px dashed #cbd5e1; border-radius:8px; color:#475569; font-size:13px;">
                        Use a Brevo <strong>v3 API key</strong> from Brevo Settings → SMTP &amp; API. The sender address must be verified in Brevo before messages can be delivered.
                        <?php if ($brevo_key_present): ?>
                            <span style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gb_brevo_test&mode=connection'), 'gb_brevo_test')); ?>" class="gb-btn-small-os">Test Brevo Connection</a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gb_brevo_test&mode=send'), 'gb_brevo_test')); ?>" class="gb-btn-small-os">Send Test Email</a>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="gb-link-row" style="margin-bottom: 20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Require Email Verification</label>
                            <select name="_gb_require_email_verification" class="gb-input" style="height: 45px;">
                                <option value="yes" <?php selected($email_verification_setting, 'yes'); ?>>Enabled for New Customer/Cleaner Accounts</option>
                                <option value="no" <?php selected($email_verification_setting, 'no'); ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Verification Email Subject</label>
                            <input type="text" name="_gb_email_verification_subject" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_email_verification_subject', true)); ?>" placeholder="Verify your email for The Green Broom">
                        </div>
                    </div>
                    <div class="gb-link-row" style="margin-bottom: 20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">From Name</label>
                            <input type="text" name="_gb_email_from_name" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_email_from_name', true)); ?>" placeholder="The Green Broom">
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">From Address</label>
                            <input type="email" name="_gb_email_from_address" class="gb-input" value="<?php echo esc_attr($from_address); ?>" placeholder="info@thegreenbroom.co.uk">
                        </div>
                    </div>
                    <div class="gb-form-row" style="margin-bottom: 0;">
                        <label class="gb-label">Reply-To Address</label>
                        <input type="email" name="_gb_email_reply_to" class="gb-input" value="<?php echo esc_attr($reply_to); ?>" placeholder="info@thegreenbroom.co.uk">
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            All site email flows inherit this sender identity. Keep the Brevo sender address verified and use the Reply-To address for responses.
                        </p>
                    </div>
                </div>
            </div>

            <div id="social" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-share"></span> Social Media & Notifications</div>
                <div class="gb-editor-body">
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Support Notification Email</label>
                        <input type="email" name="_gb_support_email" class="gb-input" value="<?php echo esc_attr($support_email); ?>" placeholder="info@thegreenbroom.co.uk">
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">New contact &amp; support messages are emailed here.</p>
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Customer Support Phone</label>
                        <input type="tel" name="_gb_support_phone" class="gb-input" value="<?php echo esc_attr($support_phone); ?>" placeholder="07974 903568">
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Shown on Contact Us with Monday to Friday, 9 am to 5 pm opening hours.</p>
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px; padding:12px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; color:#166534; font-size:13px;">
                        <strong>WhatsApp / FaceTime call:</strong> booking link coming soon.
                    </div>
                    <div style="margin:0 0 8px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Social Media Links</div>
                    <p style="margin:0 0 16px; color:#64748b; font-size:12px;">Add a full URL to show that icon in the site footer. Leave a field blank and it stays hidden.</p>
                    <div class="gb-link-row" style="margin-bottom:20px;">
                        <div class="gb-form-row"><label class="gb-label">Facebook URL</label><input type="url" name="_gb_social_facebook" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_facebook', true)); ?>" placeholder="https://facebook.com/yourpage"></div>
                        <div class="gb-form-row"><label class="gb-label">Instagram URL</label><input type="url" name="_gb_social_instagram" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_instagram', true)); ?>" placeholder="https://instagram.com/yourhandle"></div>
                    </div>
                    <div class="gb-link-row" style="margin-bottom:20px;">
                        <div class="gb-form-row"><label class="gb-label">X / Twitter URL</label><input type="url" name="_gb_social_twitter" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_twitter', true)); ?>" placeholder="https://x.com/yourhandle"></div>
                        <div class="gb-form-row"><label class="gb-label">LinkedIn URL</label><input type="url" name="_gb_social_linkedin" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_linkedin', true)); ?>" placeholder="https://linkedin.com/company/yourcompany"></div>
                    </div>
                    <div class="gb-link-row" style="margin-bottom:22px;">
                        <div class="gb-form-row"><label class="gb-label">TikTok URL</label><input type="url" name="_gb_social_tiktok" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_tiktok', true)); ?>" placeholder="https://tiktok.com/@yourhandle"></div>
                        <div class="gb-form-row"><label class="gb-label">YouTube URL</label><input type="url" name="_gb_social_youtube" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_youtube', true)); ?>" placeholder="https://youtube.com/@yourchannel"></div>
                    </div>
                    <div style="margin:0 0 8px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Google Reviews</div>
                    <p style="margin:0 0 12px; color:#64748b; font-size:12px;">Install a reviews plugin (e.g. "Widgets for Google Reviews" by Trustindex), connect your Google Business profile, then paste its shortcode here to show reviews on the homepage. Leave blank to hide.</p>
                    <div class="gb-form-row" style="margin-bottom:0;">
                        <label class="gb-label">Google Reviews Shortcode / Embed</label>
                        <input type="text" name="_gb_reviews_embed" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_reviews_embed', true)); ?>" placeholder="[trustindex no-registration=google]">
                    </div>
                </div>
            </div>

            <div id="documents" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-media-document"></span> Downloadable Documents</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 16px; padding:12px 14px; border:1px dashed #cbd5e1; border-radius:8px; color:#475569; font-size:13px;">
                        The PDFs linked around the site (Eco Products, the cleaner and customer portals, the insurance certificate). Upload here to replace the built-in file &mdash; no developer deploy needed. Leave blank to keep the bundled version.
                    </div>
                    <?php foreach (gb_get_resource_definitions() as $gb_res_key => $gb_res): ?>
                        <?php
                        $gb_res_meta = '_gb_resource_' . sanitize_key($gb_res_key);
                        $gb_res_id   = (int) get_post_meta($settings_id, $gb_res_meta, true);
                        $gb_res_name = $gb_res_id ? basename((string) get_attached_file($gb_res_id)) : '';
                        $gb_res_live = function_exists('gb_resource_is_available') && gb_resource_is_available($gb_res_key);
                        ?>
                        <div class="gb-doc-row" style="border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:12px;">
                            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                <div style="flex:1 1 220px; min-width:0;">
                                    <div style="font-weight:700; color:#0f172a; font-size:13px;"><?php echo esc_html($gb_res['label']); ?></div>
                                    <div class="gb-doc-current" style="font-size:11.5px; color:<?php echo $gb_res_live ? '#166534' : '#b45309'; ?>; margin-top:3px;">
                                        <?php
                                        if ($gb_res_name) {
                                            echo 'Uploaded: ' . esc_html($gb_res_name);
                                        } elseif ($gb_res_live) {
                                            echo 'Using built-in file (' . esc_html($gb_res['file']) . ')';
                                        } else {
                                            echo 'No file available yet';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <input type="hidden" name="<?php echo esc_attr($gb_res_meta); ?>" value="<?php echo esc_attr($gb_res_id); ?>">
                                <button type="button" class="gb-btn-small-os gb-doc-pick">Upload / Replace</button>
                                <button type="button" class="gb-btn-small-os gb-doc-clear" style="color:#991b1b;"<?php echo $gb_res_id ? '' : ' hidden'; ?>>Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="agreement" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-media-document"></span> Cleaner Terms of Association (E-Signature)</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 16px; padding:12px 14px; border:1px dashed #cbd5e1; border-radius:8px; color:#475569; font-size:13px;">
                        This is the agreement each cleaner must sign (type name + title + tick to accept) before they can go live. Edit the wording below — it takes effect immediately for anyone who has not yet signed. Signed copies are viewable and printable from each cleaner's profile.
                    </div>
                    <div class="gb-form-row" style="margin-bottom:0;">
                        <label class="gb-label">Agreement Text</label>
                        <textarea name="_gb_terms_of_association" class="gb-input" style="min-height:260px; font-family:inherit; line-height:1.5;"><?php echo esc_textarea(get_post_meta($settings_id, '_gb_terms_of_association', true)); ?></textarea>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Basic HTML is allowed. Leave blank to use the built-in default wording.</p>
                    </div>
                </div>
            </div>

            <div id="monetisation" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-chart-area"></span> Tax & Monetisation</div>
                <div class="gb-editor-body">
                    <div class="gb-link-row" style="margin-bottom: 20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Platform Application Fee</label>
                            <div class="gb-input" style="background:#f8fafc; color:#166534; font-weight:700;">No platform fee</div>
                            <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Customer payments are passed through without the previous 10% application fee.</p>
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">VAT Status</label>
                            <select name="_gb_vat_inclusive" class="gb-input" style="height: 45px;">
                                <option value="no" <?php selected(get_post_meta($settings_id, '_gb_vat_inclusive', true), 'no'); ?>>No VAT Applied</option>
                                <option value="yes" <?php selected(get_post_meta($settings_id, '_gb_vat_inclusive', true), 'yes'); ?>>Prices Inclusive of VAT</option>
                            </select>
                        </div>
                    </div>
                    <div class="gb-form-row" style="margin-bottom: 0;">
                        <label class="gb-label">Registered VAT Number (Optional)</label>
                        <input type="text" name="_gb_vat_number" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_vat_number', true)); ?>" placeholder="e.g. GB 123 4567 89">
                    </div>
                </div>
            </div>

            <div id="stripe" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-shield"></span> Stripe Connect Credentials</div>
                <div class="gb-editor-body">
                    <?php if (!empty($stripe_missing)): ?>
                        <div style="margin:0 0 14px; padding:10px 12px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; color:#991b1b; font-size:13px;">
                            Stripe setup incomplete for current mode. Fill in: <?php echo esc_html(implode(', ', $stripe_missing)); ?>.
                        </div>
                    <?php else: ?>
                        <div style="margin:0 0 14px; padding:10px 12px; border:1px solid #dcfce7; border-radius:8px; background:#f0fdf4; color:#166534; font-size:13px;">
                            Stripe credentials look complete for the active mode.
                        </div>
                    <?php endif; ?>
                    <p style="margin:0 0 18px; color:#64748b; font-size:13px;">
                        Active mode above determines which key set is used in booking/payment flows.
                    </p>
                    <div style="margin:0 0 18px; padding:12px 14px; border:1px dashed #cbd5e1; border-radius:8px; color:#475569; font-size:13px;">
                        Sandbox checklist: keep the platform in <strong>Test Mode</strong>, paste the test publishable and secret keys plus both webhook secrets, use the cleaner onboarding buttons in <strong>Manage Cleaners</strong>, then run a sample checkout below or from a live booking flow. When you are satisfied, switch to <strong>Live Mode</strong> and add the live keys.
                    </div>
                    <div class="gb-form-row" style="margin-bottom:20px;">
                        <label class="gb-label">Stripe Webhook Endpoint URL</label>
                        <input type="text" class="gb-input" value="<?php echo esc_attr(function_exists('gb_get_stripe_webhook_url') ? gb_get_stripe_webhook_url() : admin_url('admin-post.php?action=gb_stripe_webhook')); ?>" readonly>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Add this URL in your Stripe Dashboard webhook settings for the active environment.
                        </p>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                            <h4 style="margin:0 0 12px; color:#0f172a;">Test Keys</h4>
                            <div class="gb-form-row">
                                <label class="gb-label">Test Publishable Key</label>
                                <input type="text" name="_gb_stripe_test_publishable_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_publishable_key', true)); ?>" placeholder="pk_test_...">
                            </div>
                            <div class="gb-form-row">
                                <label class="gb-label">Test Secret Key</label>
                                <input type="password" name="_gb_stripe_test_secret_key" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_secret_key', true) ? 'Saved — leave blank to keep' : 'sk_test_...'); ?>" autocomplete="new-password">
                            </div>
                            <div class="gb-form-row" style="margin-bottom:0;">
                                <label class="gb-label">Test Webhook Secret</label>
                                <input type="password" name="_gb_stripe_test_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_webhook_secret', true) ? 'Saved — leave blank to keep' : 'whsec_...'); ?>" autocomplete="new-password">
                            </div>
                            <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                                <label class="gb-label">Test Connect Webhook Secret</label>
                                <input type="password" name="_gb_stripe_test_connect_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_connect_webhook_secret', true) ? 'Saved — leave blank to keep' : 'whsec_...'); ?>" autocomplete="new-password">
                            </div>
                        </div>

                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                            <h4 style="margin:0 0 12px; color:#0f172a;">Live Keys</h4>
                            <div class="gb-form-row">
                                <label class="gb-label">Live Publishable Key</label>
                                <input type="text" name="_gb_stripe_live_publishable_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_publishable_key', true)); ?>" placeholder="pk_live_...">
                            </div>
                            <div class="gb-form-row">
                                <label class="gb-label">Live Secret Key</label>
                                <input type="password" name="_gb_stripe_live_secret_key" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_secret_key', true) ? 'Saved — leave blank to keep' : 'sk_live_...'); ?>" autocomplete="new-password">
                            </div>
                            <div class="gb-form-row" style="margin-bottom:0;">
                                <label class="gb-label">Live Webhook Secret</label>
                                <input type="password" name="_gb_stripe_live_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_webhook_secret', true) ? 'Saved — leave blank to keep' : 'whsec_...'); ?>" autocomplete="new-password">
                            </div>
                            <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                                <label class="gb-label">Live Connect Webhook Secret</label>
                                <input type="password" name="_gb_stripe_live_connect_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_connect_webhook_secret', true) ? 'Saved — leave blank to keep' : 'whsec_...'); ?>" autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <div class="gb-form-row" style="margin-top:20px;">
                        <label class="gb-label">Legacy Fallback Publishable Key (Optional)</label>
                        <input type="text" name="_gb_stripe_publishable_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_publishable_key', true)); ?>" placeholder="Optional - legacy compatibility">
                    </div>
                    <div class="gb-form-row">
                        <label class="gb-label">Legacy Fallback Secret Key (Optional)</label>
                        <input type="password" name="_gb_stripe_secret_key" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_secret_key', true) ? 'Saved — leave blank to keep' : 'Optional - legacy compatibility'); ?>" autocomplete="new-password">
                    </div>
                    <div class="gb-form-row" style="margin-bottom:0;">
                        <label class="gb-label">Legacy Fallback Webhook Secret (Optional)</label>
                        <input type="password" name="_gb_stripe_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_webhook_secret', true) ? 'Saved — leave blank to keep' : 'Optional - legacy compatibility'); ?>" autocomplete="new-password">
                    </div>
                    <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                        <label class="gb-label">Legacy Fallback Connect Webhook Secret (Optional)</label>
                        <input type="password" name="_gb_stripe_connect_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_connect_webhook_secret', true) ? 'Saved — leave blank to keep' : 'Optional - legacy compatibility'); ?>" autocomplete="new-password">
                    </div>
                </div>
            </div>
        </form>

        <div id="stripe-tools" class="gb-editor-section gb-settings-panel">
            <div class="gb-editor-header"><span class="dashicons dashicons-cart"></span> Stripe Connect Commerce Toolkit (Sample)</div>
            <div class="gb-editor-body">
                <?php if ($stripe_notice !== '' && $stripe_msg !== ''): ?>
                    <div style="margin:0 0 14px; padding:10px 12px; border:1px solid <?php echo $stripe_notice === 'ok' ? '#dcfce7' : '#fecaca'; ?>; border-radius:8px; background:<?php echo $stripe_notice === 'ok' ? '#f0fdf4' : '#fef2f2'; ?>; color:<?php echo $stripe_notice === 'ok' ? '#166534' : '#991b1b'; ?>; font-size:13px;">
                        <?php echo esc_html($stripe_msg); ?>
                    </div>
                <?php endif; ?>

                <p style="margin:0 0 14px; color:#64748b; font-size:13px;">
                    Product creation and management now runs from the Services tab. Use this section to preview storefront behavior powered by that catalog. It always follows the active environment above, so it is safe to exercise in Stripe test mode.
                </p>
                <a href="<?php echo esc_url(home_url('/manager-dashboard?tab=services')); ?>" class="gb-btn-small-os" style="margin-bottom:16px;">Open Services Catalog</a>
                <?php echo do_shortcode('[gb_stripe_storefront]'); ?>
            </div>
        </div>

        <div id="account" class="gb-editor-section gb-settings-panel">
            <div class="gb-editor-header"><span class="dashicons dashicons-admin-users"></span> Your Account &amp; Password</div>
            <div class="gb-editor-body">
                <?php echo function_exists('gb_password_notice_html') ? gb_password_notice_html() : ''; ?>
                <p style="margin:0 0 18px; color:#64748b; font-size:13px;">Change the password for your manager login (<strong><?php echo esc_html(wp_get_current_user()->user_login); ?></strong>). You'll stay logged in after updating.</p>
                <form method="post" action="<?php echo esc_url(home_url('/manager-dashboard')); ?>" style="max-width:460px;">
                    <input type="hidden" name="gb_pw_action" value="change_password">
                    <input type="hidden" name="gb_pw_redirect" value="<?php echo esc_url(home_url('/manager-dashboard?tab=settings')); ?>">
                    <?php wp_nonce_field('gb_change_password', 'gb_pw_nonce'); ?>
                    <div class="gb-form-row">
                        <label class="gb-label">Current Password</label>
                        <input type="password" name="gb_current_password" class="gb-input" required autocomplete="current-password">
                    </div>
                    <div class="gb-form-row">
                        <label class="gb-label">New Password (min. 8 characters)</label>
                        <input type="password" name="gb_new_password" class="gb-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="gb-form-row">
                        <label class="gb-label">Confirm New Password</label>
                        <input type="password" name="gb_confirm_password" class="gb-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="gb-header-btn" style="background:#2e7d32; color:#fff; border:none; height:44px; padding:0 24px; border-radius:8px; font-weight:700; cursor:pointer;">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var shell = document.querySelector('[data-settings-shell]');
    if (!shell) return;

    document.documentElement.classList.add('gb-settings-js');
    var panels = Array.prototype.slice.call(shell.querySelectorAll('.gb-settings-panel'));
    var links = Array.prototype.slice.call(shell.querySelectorAll('[data-settings-target]'));
    var defaultSection = <?php echo wp_json_encode($active_settings_section); ?>;

    function activate(section, updateUrl) {
        var target = panels.find(function (panel) { return panel.id === section; });
        if (!target) {
            section = defaultSection;
            target = panels.find(function (panel) { return panel.id === section; });
        }

        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel === target);
        });
        links.forEach(function (link) {
            var active = link.getAttribute('data-settings-target') === section;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });

        if (updateUrl) {
            window.history.replaceState({}, '', window.location.pathname + '?tab=settings&section=' + encodeURIComponent(section));
        }
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    links.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            activate(link.getAttribute('data-settings-target'), true);
        });
    });

    var hashSection = window.location.hash ? window.location.hash.slice(1) : '';
    activate(hashSection || defaultSection, false);
})();
</script>

<script>
jQuery(document).ready(function ($) {
    $('.gb-doc-pick').on('click', function (e) {
        e.preventDefault();
        var $row = $(this).closest('.gb-doc-row');
        var frame = wp.media({
            title: 'Select or upload a document',
            button: { text: 'Use this file' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $row.find('input[type=hidden]').val(att.id);
            $row.find('.gb-doc-current').css('color', '#166534').text('Uploaded: ' + (att.filename || att.title));
            $row.find('.gb-doc-clear').prop('hidden', false);
        });
        frame.open();
    });

    $('.gb-doc-clear').on('click', function (e) {
        e.preventDefault();
        var $row = $(this).closest('.gb-doc-row');
        $row.find('input[type=hidden]').val('');
        $row.find('.gb-doc-current').css('color', '#b45309').text('Will revert to the built-in file on save');
        $(this).prop('hidden', true);
    });
});
</script>
