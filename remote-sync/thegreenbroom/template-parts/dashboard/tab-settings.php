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
?>

<style>
    /* Ensure the tab uses the same Layout Tokens as tab-pages.php */
    .gb-builder-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
    .gb-builder-layout { display: grid; grid-template-columns: 1fr; gap: 24px; align-items: start; }
    .gb-builder-nav { display: none; }
    .gb-nav-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; position: sticky; top: 20px; }
    .gb-editor-section { background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px; overflow: hidden; }
    .gb-editor-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
    .gb-editor-header .dashicons { font-size: 16px; color: #64748b; }
    .gb-editor-body { padding: 25px; }
    .gb-form-row { margin-bottom: 20px; }
    .gb-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .gb-input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
    .gb-link-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .gb-btn-small-os { text-decoration: none; padding: 8px 16px; border: 1px solid #e2e8f0; border-radius: 6px; color: #334155; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; transition: all 0.2s ease; background: white; cursor: pointer; }
</style>

<div class="booking-controls">
    <div>
        <a href="?tab=overview" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Dashboard</a>
        <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a;">Platform Settings</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Control payment environment, safeguarding rules, and platform configuration.</p>
    </div>
    <div style="display:flex; gap:12px;">
        <button type="submit" form="gb-settings-form" class="gb-header-btn" style="height:44px; background:#2e7d32; color:white; border:none; padding:0 30px; border-radius:8px; font-weight:700; cursor:pointer;">Update Settings</button>
    </div>
</div>

<div class="gb-builder-layout">
    <aside class="gb-builder-nav">
        <div class="gb-nav-card">
            <label class="gb-label">Quick Jump</label>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <a href="#env" class="gb-btn-small-os" style="text-align:left;">🌐 Environment</a>
                <a href="#safeguarding" class="gb-btn-small-os" style="text-align:left;">🛡️ Safeguarding</a>
                <a href="#email" class="gb-btn-small-os" style="text-align:left;">✉️ Email</a>
                <a href="#monetisation" class="gb-btn-small-os" style="text-align:left;">💰 Monetisation</a>
                <a href="#stripe" class="gb-btn-small-os" style="text-align:left;">🔑 Stripe Keys</a>
                <a href="#stripe-tools" class="gb-btn-small-os" style="text-align:left;">🛒 Stripe Tools</a>
            </div>
        </div>
    </aside>

    <div class="gb-builder-content">
        <form method="post" id="gb-settings-form">
            <input type="hidden" name="gb_save_action" value="save_settings">
            <input type="hidden" name="post_id" value="<?php echo $settings_id; ?>">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

            <div id="env" class="gb-editor-section">
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

            <div id="safeguarding" class="gb-editor-section">
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

            <div id="email" class="gb-editor-section">
                <div class="gb-editor-header"><span class="dashicons dashicons-email-alt"></span> Email & Delivery</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 18px; padding:12px 14px; border:1px dashed #cbd5e1; border-radius:8px; color:#475569; font-size:13px;">
                        The platform sends account verification and password reset emails through WordPress <strong>wp_mail</strong>. Set the sender identity here now, then connect SMTP or a transactional mail service later and these flows will use it automatically.
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
                            <input type="email" name="_gb_email_from_address" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_email_from_address', true)); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        </div>
                    </div>
                    <div class="gb-form-row" style="margin-bottom: 0;">
                        <label class="gb-label">Reply-To Address</label>
                        <input type="email" name="_gb_email_reply_to" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_email_reply_to', true)); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Leave sender fields blank to fall back to the WordPress admin email. Once you connect SMTP or an email API plugin, password reset and verification mail will inherit the same sender identity.
                        </p>
                    </div>
                </div>
            </div>

            <div id="social" class="gb-editor-section">
                <div class="gb-editor-header"><span class="dashicons dashicons-share"></span> Social Media & Notifications</div>
                <div class="gb-editor-body">
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Support Notification Email</label>
                        <input type="email" name="_gb_support_email" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_support_email', true)); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">New contact &amp; support messages are emailed here. Leave blank to use the WordPress admin email.</p>
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
                    <div class="gb-link-row" style="margin-bottom:0;">
                        <div class="gb-form-row"><label class="gb-label">TikTok URL</label><input type="url" name="_gb_social_tiktok" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_tiktok', true)); ?>" placeholder="https://tiktok.com/@yourhandle"></div>
                        <div class="gb-form-row"><label class="gb-label">YouTube URL</label><input type="url" name="_gb_social_youtube" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_youtube', true)); ?>" placeholder="https://youtube.com/@yourchannel"></div>
                    </div>
                </div>
            </div>

            <div id="monetisation" class="gb-editor-section">
                <div class="gb-editor-header"><span class="dashicons dashicons-chart-area"></span> Tax & Monetisation</div>
                <div class="gb-editor-body">
                    <div class="gb-link-row" style="margin-bottom: 20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Platform Application Fee (%)</label>
                            <input type="number" name="_gb_platform_fee" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_platform_fee', true) ?: '10'); ?>" step="0.1">
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

            <div id="stripe" class="gb-editor-section">
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
                                <input type="password" name="_gb_stripe_test_secret_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_secret_key', true)); ?>" placeholder="sk_test_...">
                            </div>
                            <div class="gb-form-row" style="margin-bottom:0;">
                                <label class="gb-label">Test Webhook Secret</label>
                                <input type="password" name="_gb_stripe_test_webhook_secret" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_webhook_secret', true)); ?>" placeholder="whsec_...">
                            </div>
                            <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                                <label class="gb-label">Test Connect Webhook Secret</label>
                                <input type="password" name="_gb_stripe_test_connect_webhook_secret" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_connect_webhook_secret', true)); ?>" placeholder="whsec_...">
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
                                <input type="password" name="_gb_stripe_live_secret_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_secret_key', true)); ?>" placeholder="sk_live_...">
                            </div>
                            <div class="gb-form-row" style="margin-bottom:0;">
                                <label class="gb-label">Live Webhook Secret</label>
                                <input type="password" name="_gb_stripe_live_webhook_secret" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_webhook_secret', true)); ?>" placeholder="whsec_...">
                            </div>
                            <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                                <label class="gb-label">Live Connect Webhook Secret</label>
                                <input type="password" name="_gb_stripe_live_connect_webhook_secret" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_connect_webhook_secret', true)); ?>" placeholder="whsec_...">
                            </div>
                        </div>
                    </div>

                    <div class="gb-form-row" style="margin-top:20px;">
                        <label class="gb-label">Legacy Fallback Publishable Key (Optional)</label>
                        <input type="text" name="_gb_stripe_publishable_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_publishable_key', true)); ?>" placeholder="Optional - legacy compatibility">
                    </div>
                    <div class="gb-form-row">
                        <label class="gb-label">Legacy Fallback Secret Key (Optional)</label>
                        <input type="password" name="_gb_stripe_secret_key" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_secret_key', true)); ?>" placeholder="Optional - legacy compatibility">
                    </div>
                    <div class="gb-form-row" style="margin-bottom:0;">
                        <label class="gb-label">Legacy Fallback Webhook Secret (Optional)</label>
                        <input type="password" name="_gb_stripe_webhook_secret" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_webhook_secret', true)); ?>" placeholder="Optional - legacy compatibility">
                    </div>
                    <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                        <label class="gb-label">Legacy Fallback Connect Webhook Secret (Optional)</label>
                        <input type="password" name="_gb_stripe_connect_webhook_secret" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_connect_webhook_secret', true)); ?>" placeholder="Optional - legacy compatibility">
                    </div>
                </div>
            </div>
        </form>

        <div id="stripe-tools" class="gb-editor-section">
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
    </div>
</div>
