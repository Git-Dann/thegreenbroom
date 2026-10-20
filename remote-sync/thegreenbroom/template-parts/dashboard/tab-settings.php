<?php
// Settings are globally stored on the Front Page ID for site-wide access
$settings_id = get_option('page_on_front');
$email_verification_setting = get_post_meta($settings_id, '_gb_require_email_verification', true);
$email_verification_setting = $email_verification_setting === '' ? 'yes' : $email_verification_setting;
$stripe_config = function_exists('gb_get_stripe_config') ? gb_get_stripe_config() : [];
/*
 * What "Stripe keys: Action needed" actually means.
 *
 * This used to demand the Connect webhook secret alongside the three that
 * matter, so a correctly configured account with working keys, a working
 * webhook and live products still showed a permanent orange warning. Nothing
 * on the platform reads that secret until cleaners are being paid through
 * Connect, so it is tracked separately and quietly.
 */
$stripe_missing = function_exists('gb_get_stripe_config_issues')
    ? gb_get_stripe_config_issues($stripe_config, ['publishable_key', 'secret_key', 'webhook_secret'])
    : [];
$stripe_connect_missing = function_exists('gb_get_stripe_config_issues')
    ? gb_get_stripe_config_issues($stripe_config, ['connect_webhook_secret'])
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
/*
 * Every settings section, grouped.
 *
 * This is the single source of truth: the sidebar is rendered from it and the
 * list of sections a URL may ask for is derived from it. They had drifted -
 * the sidebar offered fifteen sections and the allow-list knew about twelve,
 * so ?section=pricing, ?section=documents and ?section=stripe-plans all
 * silently dumped you on Environment. Deriving one from the other means that
 * cannot happen again.
 *
 * The order inside each group is the order somebody actually works through
 * them: pick the mode, add the keys, set the prices, then create the plans.
 */
$gb_settings_groups = [
    'Payments' => [
        ['stripe',       '🔑', 'Stripe credentials', 'Mode, keys and webhooks'],
        ['pricing',      '£',  'Membership pricing', 'What the public site shows'],
        ['stripe-plans', '🎟', 'Membership plans',   'Products in Stripe'],
        ['monetisation', '%',  'Tax & fees',         'VAT settings'],
        ['stripe-tools', '🛒', 'Stripe tools',       'Storefront preview'],
    ],
    'Members & cleaners' => [
        ['membership',   '🔑', 'Customer access',    'Who can browse cleaners'],
        ['safeguarding', '🛡', 'Safeguarding',       'Booking rules'],
        ['agreement',    '▤',  'Cleaner agreement',  'Terms and signature'],
    ],
    'Site & content' => [
        ['social',       '↗',  'Contact & social',   'Public details'],
        ['cookies',      '🍪', 'Cookies & consent',  'Banner wording'],
        ['documents',    '▦',  'Documents',          'Downloadable PDFs'],
        ['email',        '✉',  'Email & delivery',   'Brevo and sender'],
    ],
    'Data & account' => [
        ['retention',    '🗄', 'Data retention',     'How long records are kept'],
        ['account',      '●',  'Your account',       'Password'],
    ],
];

$settings_sections = [];
foreach ($gb_settings_groups as $gb_group_items) {
    foreach ($gb_group_items as $gb_item) {
        $settings_sections[] = $gb_item[0];
    }
}

$active_settings_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'stripe';
if (!in_array($active_settings_section, $settings_sections, true)) {
    // ?section=env still lands here: Environment was folded into this screen.
    $active_settings_section = 'stripe';
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
    html.gb-settings-js .gb-settings-panel:not(.is-active) { display:none; }
    .gb-input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
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
    }
</style>

<div class="booking-controls gb-settings-header">
    <div>
        <a href="?tab=overview" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Dashboard</a>
        <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a;">Platform Settings</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Control payment environment, safeguarding rules, and platform configuration.</p>
        <div class="gb-settings-summary" aria-label="Settings status">
            <span class="<?php echo $stripe_mode === 'live' ? 'is-warn' : 'is-good'; ?>">Stripe: <?php echo esc_html($stripe_mode_label); ?></span>
            <?php $gb_mail_attention = function_exists('gb_outbox_needs_attention') ? gb_outbox_needs_attention() : ['any' => false]; ?>
            <span class="<?php echo $brevo_enabled && $brevo_key_present && empty($gb_mail_attention['any']) ? 'is-good' : 'is-warn'; ?>">Email: <?php echo !($brevo_enabled && $brevo_key_present) ? 'Needs setup' : (empty($gb_mail_attention['any']) ? 'Healthy' : 'Needs a look'); ?></span>
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
            <?php foreach ($gb_settings_groups as $gb_group_label => $gb_group_items): ?>
                <p class="gb-settings-nav-group"><?php echo esc_html($gb_group_label); ?></p>
                <?php foreach ($gb_group_items as $gb_item): ?>
                    <a href="#<?php echo esc_attr($gb_item[0]); ?>" class="gb-settings-nav-link" data-settings-target="<?php echo esc_attr($gb_item[0]); ?>"><span class="gb-settings-nav-icon"><?php echo esc_html($gb_item[1]); ?></span><span><?php echo esc_html($gb_item[2]); ?><small><?php echo esc_html($gb_item[3]); ?></small></span></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="gb-builder-content gb-settings-content">
        <form method="post" id="gb-settings-form">
            <input type="hidden" name="gb_save_action" value="save_settings">
            <input type="hidden" name="post_id" value="<?php echo $settings_id; ?>">
            <input type="hidden" name="_gb_settings_section" value="<?php echo esc_attr($active_settings_section); ?>">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

            <div id="safeguarding" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-shield-alt"></span> Standards & Safeguarding</div>
                <div class="gb-editor-body">
                    <?php
                    /*
                     * Where ID documents and DBS certificates are kept.
                     *
                     * This host serves files out of wp-content/uploads even
                     * where an .htaccess says not to, so the plugin moves them
                     * above the web root, where no request can reach them at
                     * all. That move used to happen silently, with the result
                     * written to an option nobody could read.
                     */
                    $gb_docs = class_exists('GB_Uploads') && method_exists('GB_Uploads', 'get_storage_status')
                        ? GB_Uploads::get_storage_status()
                        : null;
                    ?>
                    <?php if (is_array($gb_docs)): ?>
                        <?php
                        $gb_docs_ok = !empty($gb_docs['secure']) && (int) $gb_docs['kept'] === 0;
                        $gb_docs_warn = empty($gb_docs['secure']) || (int) $gb_docs['kept'] > 0;
                        ?>
                        <div style="margin:0 0 22px; padding:13px 15px; border-radius:9px; font-size:13px; line-height:1.6;
                            background:<?php echo $gb_docs_ok ? '#f0fdf4' : ($gb_docs_warn ? '#fffbeb' : '#f8fafc'); ?>;
                            border:1px solid <?php echo $gb_docs_ok ? '#bbf7d0' : ($gb_docs_warn ? '#fde68a' : '#e2e8f0'); ?>;
                            color:<?php echo $gb_docs_ok ? '#166534' : ($gb_docs_warn ? '#92400e' : '#475569'); ?>;">
                            <strong>Verification documents</strong> &mdash; stored <?php echo esc_html($gb_docs['store']); ?>.
                            <?php if ((int) $gb_docs['kept'] > 0): ?>
                                <br><?php echo (int) $gb_docs['kept']; ?> file<?php echo (int) $gb_docs['kept'] === 1 ? '' : 's'; ?> could not be moved out of the old location and may still be reachable by anyone who knows the address. Worth raising with your developer.
                            <?php elseif ((int) $gb_docs['kept'] === 0): ?>
                                <br>Nothing left in the old location<?php echo $gb_docs['checked'] !== '' ? ' as of ' . esc_html($gb_docs['checked']) : ''; ?>. ID and DBS files cannot be requested over the web.
                            <?php else: ?>
                                <br>The move has not run yet.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
                            <?php
                            // Read through the same function the refunds use,
                            // so a saved 0 shows as 0 rather than the default.
                            $gb_refund_policy = function_exists('gb_refund_policy_settings') ? gb_refund_policy_settings() : ['window_hours' => 48, 'partial_percent' => 50];
                            ?>
                            <input type="number" name="_gb_cancel_window" class="gb-input" min="0" step="1" value="<?php echo esc_attr($gb_refund_policy['window_hours']); ?>">
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Eco-Standard Agreement</label>
                            <select name="_gb_eco_commitment" class="gb-input" style="height: 45px;">
                                <option value="yes" <?php selected(get_post_meta($settings_id, '_gb_eco_commitment', true), 'yes'); ?>>Enforced at Signup</option>
                                <option value="no" <?php selected(get_post_meta($settings_id, '_gb_eco_commitment', true), 'no'); ?>>Information Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="gb-link-row" style="margin-top: 20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Refund if cancelled inside the window (%)</label>
                            <input type="number" name="_gb_cancel_partial_percent" class="gb-input" min="0" max="100" step="1" value="<?php echo esc_attr($gb_refund_policy['partial_percent']); ?>">
                        </div>
                    </div>
                    <p style="margin:8px 0 0; color:#64748b; font-size:12px; line-height:1.6;">
                        When a customer cancels more than the window before the clean, they get a full refund. Inside the window, they get this percentage back. Once the clean has started, there is no refund. If the cleaner or the team cancels, the customer is always refunded in full. Refunds go back to the customer's card automatically, and the cleaner's share is taken back from their Stripe account in proportion.
                    </p>
                </div>
            </div>

            <div id="email" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-email-alt"></span> Email & Delivery</div>
                <div class="gb-editor-body">
                    <?php
                    /*
                     * Delivery health: every route, whether it is working,
                     * and what happened to the emails actually sent.
                     *
                     * On 29 September a signup could not get in because Brevo
                     * refused the site and nobody knew until she said so. This
                     * panel, the checks behind it and the alert email are there
                     * so that the next time something breaks it is found here,
                     * by us, first.
                     */
                    $gb_health      = function_exists('gb_outbox_health') ? gb_outbox_health() : null;
                    $gb_counts      = function_exists('gb_outbox_counts') ? gb_outbox_counts(24) : ['sent' => 0, 'retrying' => 0, 'dead' => 0, 'server_mail' => 0];
                    $gb_backup      = function_exists('gb_get_backup_smtp_settings') ? gb_get_backup_smtp_settings() : null;
                    $gb_backup_on   = $gb_backup && function_exists('gb_backup_smtp_configured') && gb_backup_smtp_configured($gb_backup);
                    $gb_brevo_ready = $brevo_enabled && $brevo_key_present;
                    $gb_brevo_fail  = get_option('gb_brevo_last_error');

                    if (!$gb_brevo_ready) {
                        $gb_route_brevo = ['warn', 'Not set up', $brevo_enabled ? 'Enabled, but the API key is missing.' : 'Turned off below.'];
                    } elseif ($gb_health && isset($gb_health['brevo']['ok']) && !$gb_health['brevo']['ok']) {
                        $gb_route_brevo = ['bad', 'Refusing the site', $gb_health['brevo']['message']];
                    } elseif (is_array($gb_brevo_fail) && !empty($gb_brevo_fail['message']) && !empty($gb_brevo_fail['at']) && (time() - (int) $gb_brevo_fail['at']) < DAY_IN_SECONDS
                        && (!$gb_health || empty($gb_health['at']) || (int) $gb_brevo_fail['at'] > (int) $gb_health['at'])) {
                        // Only while nothing newer says it is fine again.
                        $gb_route_brevo = ['warn', 'Refused an email today', $gb_brevo_fail['message']];
                    } elseif ($gb_health) {
                        $gb_route_brevo = ['good', 'Working', 'Branded emails go out through Brevo.'];
                    } else {
                        $gb_route_brevo = ['idle', 'Not checked yet', 'Press Check now.'];
                    }

                    if (!$gb_backup_on) {
                        $gb_route_backup = ['warn', 'Not set up', 'If Brevo refuses, email falls straight to the web server, which may land in spam. Set this up below.'];
                    } elseif ($gb_health && !empty($gb_health['backup']['configured']) && empty($gb_health['backup']['ok'])) {
                        $gb_route_backup = ['bad', 'Cannot sign in', $gb_health['backup']['message']];
                    } elseif ($gb_health && !empty($gb_health['backup']['ok'])) {
                        $gb_route_backup = ['good', 'Standing by', $gb_health['backup']['message']];
                    } else {
                        $gb_route_backup = ['idle', 'Not checked yet', 'Press Check now.'];
                    }

                    $gb_route_rows = [
                        ['1', 'Brevo', 'Main route', $gb_route_brevo],
                        ['2', 'Backup mailbox', 'If Brevo refuses', $gb_route_backup],
                        ['3', 'Web server', 'Last resort', ['idle', 'Always available', 'Unsigned, so it may land in spam. Only used when both of the above fail.']],
                    ];
                    ?>
                    <style>
                        .gb-mail-health { margin:0 0 22px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; overflow:hidden; }
                        .gb-mail-health__head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; padding:14px 16px; border-bottom:1px solid #eef2f7; background:#f8fafc; }
                        .gb-mail-health__title { margin:0; font-size:14px; font-weight:800; color:#0f172a; }
                        .gb-mail-health__meta { margin:2px 0 0; font-size:12px; color:#64748b; }
                        .gb-mail-route { display:grid; grid-template-columns:28px minmax(0,170px) minmax(0,1fr); gap:12px; align-items:start; padding:13px 16px; border-bottom:1px solid #f1f5f9; }
                        .gb-mail-route:last-of-type { border-bottom:0; }
                        .gb-mail-route__n { width:24px; height:24px; border-radius:50%; background:#f1f5f9; color:#475569; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; }
                        .gb-mail-route__name { font-size:13px; font-weight:800; color:#0f172a; }
                        .gb-mail-route__role { display:block; font-size:11px; color:#94a3b8; font-weight:600; }
                        .gb-mail-pill { display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:800; }
                        .gb-mail-pill::before { content:""; width:7px; height:7px; border-radius:50%; background:currentColor; }
                        .gb-mail-pill.is-good { background:#f0fdf4; color:#166534; }
                        .gb-mail-pill.is-warn { background:#fffbeb; color:#92400e; }
                        .gb-mail-pill.is-bad  { background:#fef2f2; color:#991b1b; }
                        .gb-mail-pill.is-idle { background:#f1f5f9; color:#475569; }
                        .gb-mail-route__detail { margin:5px 0 0; font-size:12.5px; line-height:1.55; color:#475569; word-break:break-word; }
                        .gb-mail-caps { padding:12px 16px 14px; border-top:1px solid #eef2f7; background:#fcfdfe; }
                        .gb-mail-caps__title { margin:0 0 8px; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#64748b; }
                        .gb-mail-cap { display:grid; grid-template-columns:128px minmax(0,1fr); gap:10px; align-items:start; padding:5px 0; font-size:12.5px; line-height:1.5; color:#475569; }
                        .gb-mail-cap .gb-mail-pill { justify-self:start; white-space:nowrap; }
                        .gb-mail-cap strong { color:#0f172a; }
                        .gb-mail-stats { display:grid; grid-template-columns:repeat(5, minmax(0,1fr)); border-top:1px solid #eef2f7; }
                        .gb-mail-stat { padding:12px 16px; border-right:1px solid #f1f5f9; }
                        .gb-mail-stat:last-child { border-right:0; }
                        .gb-mail-stat strong { display:block; font-size:20px; color:#0f172a; line-height:1.1; }
                        .gb-mail-stat span { font-size:11px; color:#64748b; font-weight:700; }
                        .gb-mail-stat.is-bad strong { color:#b91c1c; }
                        .gb-mail-stat.is-warn strong { color:#b45309; }
                        .gb-mail-actions { display:flex; flex-wrap:wrap; gap:8px; padding:12px 16px; border-top:1px solid #eef2f7; background:#fcfdfe; }
                        .gb-mail-sub { margin:26px 0 12px; padding-top:20px; border-top:1px solid #eef2f7; font-size:14px; font-weight:800; color:#0f172a; }
                        .gb-mail-sub small { display:block; margin-top:3px; font-size:12px; font-weight:500; color:#64748b; line-height:1.55; }
                        .gb-mail-log { width:100%; border-collapse:collapse; font-size:12.5px; }
                        .gb-mail-log th { text-align:left; padding:8px 10px; font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; border-bottom:1px solid #e2e8f0; }
                        .gb-mail-log td { padding:10px; border-bottom:1px solid #f1f5f9; vertical-align:top; color:#334155; }
                        .gb-mail-log__subject { font-weight:700; color:#0f172a; }
                        .gb-mail-log__why { margin-top:4px; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:11.5px; color:#991b1b; white-space:pre-wrap; word-break:break-word; }
                        .gb-mail-log__why.is-resolved { color:#64748b; }
                        .gb-mail-log__btns { display:flex; gap:6px; flex-wrap:wrap; }
                        .gb-mail-log-wrap { border:1px solid #e2e8f0; border-radius:10px; overflow-x:auto; }
                        @media (max-width: 720px) {
                            .gb-mail-route { grid-template-columns:28px minmax(0,1fr); }
                            .gb-mail-route > div:last-child { grid-column:2; }
                            .gb-mail-stats { grid-template-columns:repeat(2, minmax(0,1fr)); }
                            .gb-mail-cap { grid-template-columns:1fr; gap:4px; padding:7px 0; }
                            .gb-mail-stat:nth-child(2n) { border-right:0; }
                            .gb-mail-log thead { display:none; }
                            .gb-mail-log, .gb-mail-log tbody, .gb-mail-log tr, .gb-mail-log td { display:block; width:100%; box-sizing:border-box; }
                            .gb-mail-log tr { padding:10px 12px; border-bottom:1px solid #eef2f7; }
                            .gb-mail-log td { padding:3px 0; border:0; white-space:normal !important; }
                        }
                    </style>
                    <div class="gb-mail-health">
                        <div class="gb-mail-health__head">
                            <div>
                                <p class="gb-mail-health__title">Delivery health</p>
                                <p class="gb-mail-health__meta">
                                    Every email tries each route in turn until one takes it. Anything that fails all three is retried automatically for six hours, and you get an alert email.
                                    <?php if ($gb_health && !empty($gb_health['at'])): ?>
                                        Last checked <?php echo esc_html(human_time_diff((int) $gb_health['at'], time())); ?> ago.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php foreach ($gb_route_rows as $gb_route): ?>
                            <div class="gb-mail-route">
                                <span class="gb-mail-route__n"><?php echo esc_html($gb_route[0]); ?></span>
                                <div>
                                    <span class="gb-mail-route__name"><?php echo esc_html($gb_route[1]); ?></span>
                                    <span class="gb-mail-route__role"><?php echo esc_html($gb_route[2]); ?></span>
                                </div>
                                <div>
                                    <span class="gb-mail-pill is-<?php echo esc_attr($gb_route[3][0]); ?>"><?php echo esc_html($gb_route[3][1]); ?></span>
                                    <p class="gb-mail-route__detail"><?php echo esc_html($gb_route[3][2]); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php
                        /*
                         * What Brevo can actually do, from the last check:
                         * signed sending, sends left, and each template the
                         * site relies on. A connection test passes with all
                         * of these broken.
                         */
                        $gb_caps = $gb_health && isset($gb_health['caps']) ? $gb_health['caps'] : null;
                        $gb_tpl_labels = [
                            'verification'         => 'Verify email',
                            'password_reset'       => 'Password reset',
                            'booking_customer'     => 'Booking confirmed (customer)',
                            'booking_cleaner'      => 'New booking (cleaner)',
                            'support_reply'        => 'Support reply',
                            'manager_message'      => 'New message (managers)',
                            'subscription_expired' => 'Subscription expired',
                        ];
                        ?>
                        <?php if ($gb_caps): ?>
                            <div class="gb-mail-caps">
                                <p class="gb-mail-caps__title">What Brevo can do</p>
                                <div class="gb-mail-cap">
                                    <span class="gb-mail-pill is-<?php echo !empty($gb_caps['domain']['ok']) ? 'good' : 'bad'; ?>"><?php echo !empty($gb_caps['domain']['ok']) ? 'Signed' : 'Not signed'; ?></span>
                                    <span><strong>Sending domain.</strong> <?php echo esc_html($gb_caps['domain']['message']); ?></span>
                                </div>
                                <div class="gb-mail-cap">
                                    <span class="gb-mail-pill is-<?php echo empty($gb_caps['credits']['ok']) ? 'bad' : (!empty($gb_caps['credits']['low']) ? 'warn' : 'good'); ?>"><?php echo isset($gb_caps['credits']['count']) ? esc_html(number_format_i18n((int) $gb_caps['credits']['count'])) . ' left' : 'Unknown'; ?></span>
                                    <span><strong>Plan.</strong> <?php echo esc_html($gb_caps['credits']['message']); ?></span>
                                </div>
                                <?php if (isset($gb_caps['webhook'])): ?>
                                    <div class="gb-mail-cap">
                                        <span class="gb-mail-pill is-<?php echo !empty($gb_caps['webhook']['ok']) ? 'good' : 'warn'; ?>"><?php echo !empty($gb_caps['webhook']['ok']) ? 'Connected' : 'Not connected'; ?></span>
                                        <span><strong>Delivery reports.</strong> <?php echo esc_html($gb_caps['webhook']['message']); ?>
                                            <?php if (empty($gb_caps['webhook']['ok']) && function_exists('gb_brevo_test_url')): ?>
                                                <a href="<?php echo esc_url(gb_brevo_test_url('webhook_connect')); ?>" style="color:#166534; font-weight:700;">Connect delivery reports</a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if (($gb_caps['designs'] ?? '') === 'site'): ?>
                                    <div class="gb-mail-cap">
                                        <span class="gb-mail-pill is-good">Site designs</span>
                                        <span><strong>Email designs.</strong> The site fills in all seven branded designs itself (<code>email-templates/</code> in the theme) and Brevo delivers them, so every route sends exactly the same email. Brevo's stored templates are not used.</span>
                                    </div>
                                <?php endif; ?>
                                <?php foreach ((array) ($gb_caps['templates'] ?? []) as $gb_tkey => $gb_t): ?>
                                    <div class="gb-mail-cap">
                                        <span class="gb-mail-pill is-<?php echo !empty($gb_t['ok']) ? 'good' : 'warn'; ?>"><?php echo !empty($gb_t['ok']) ? 'Ready' : 'Using site copy'; ?></span>
                                        <span><strong><?php echo esc_html($gb_tpl_labels[$gb_tkey] ?? $gb_tkey); ?>.</strong> <?php echo esc_html($gb_t['message']); ?><?php if (empty($gb_t['ok'])): ?> The site sends its own copy of this design instead, so it still arrives branded.<?php endif; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="gb-mail-stats" aria-label="Emails in the last 24 hours">
                            <div class="gb-mail-stat"><strong><?php echo (int) $gb_counts['sent']; ?></strong><span>Delivered, 24h</span></div>
                            <div class="gb-mail-stat <?php echo $gb_counts['retrying'] ? 'is-warn' : ''; ?>"><strong><?php echo (int) $gb_counts['retrying']; ?></strong><span>Waiting to retry</span></div>
                            <div class="gb-mail-stat <?php echo $gb_counts['dead'] ? 'is-bad' : ''; ?>"><strong><?php echo (int) $gb_counts['dead']; ?></strong><span>Not delivered</span></div>
                            <div class="gb-mail-stat <?php echo $gb_counts['bounced'] ? 'is-warn' : ''; ?>"><strong><?php echo (int) $gb_counts['bounced']; ?></strong><span>Bounced</span></div>
                            <div class="gb-mail-stat <?php echo $gb_counts['server_mail'] ? 'is-warn' : ''; ?>"><strong><?php echo (int) $gb_counts['server_mail']; ?></strong><span>Via web server</span></div>
                        </div>
                        <?php if (function_exists('gb_brevo_test_url')): ?>
                            <div class="gb-mail-actions">
                                <?php // Front-end route: /wp-admin/ is closed to managers, so admin-post.php never ran for them. ?>
                                <a href="<?php echo esc_url(gb_brevo_test_url('health')); ?>" class="gb-btn-small-os">Check now</a>
                                <?php if ($brevo_key_present): ?>
                                    <a href="<?php echo esc_url(gb_brevo_test_url('connection')); ?>" class="gb-btn-small-os">Test Brevo connection</a>
                                    <a href="<?php echo esc_url(gb_brevo_test_url('send')); ?>" class="gb-btn-small-os">Send test via Brevo</a>
                                <?php endif; ?>
                                <?php if ($gb_backup_on): ?>
                                    <a href="<?php echo esc_url(gb_brevo_test_url('backup_connect')); ?>" class="gb-btn-small-os">Test backup sign-in</a>
                                    <a href="<?php echo esc_url(gb_brevo_test_url('backup_send')); ?>" class="gb-btn-small-os">Send test via backup</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
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
                        Under Brevo Settings → Security → Authorised IPs, either turn blocking off for API keys or keep <strong>217.160.134.0/24</strong> on the list: that is the web host's range, and a single missing address there is what stopped email on 29 September.
                    </div>
                    <p class="gb-mail-sub">Backup mailbox
                        <small>A real mailbox on the domain, used whenever Brevo refuses. It is a different company from Brevo with its own servers and password, and it is named in the domain's SPF record, so these emails are trusted by Gmail and Outlook. Use the mailbox the site sends from (usually info@thegreenbroom.co.uk) and its own password from the FastHosts control panel.</small>
                    </p>
                    <div class="gb-link-row" style="margin-bottom:20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Use the Backup Mailbox</label>
                            <select name="_gb_backup_smtp_enabled" class="gb-input" style="height:45px;">
                                <option value="yes" <?php selected($gb_backup && $gb_backup['enabled'], true); ?>>Enabled</option>
                                <option value="no" <?php selected($gb_backup && $gb_backup['enabled'], false); ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Mailbox Address</label>
                            <input type="email" name="_gb_backup_smtp_user" class="gb-input" value="<?php echo esc_attr($gb_backup ? $gb_backup['username'] : ''); ?>" placeholder="info@thegreenbroom.co.uk" autocomplete="off">
                        </div>
                    </div>
                    <div class="gb-link-row" style="margin-bottom:20px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Mailbox Password</label>
                            <input type="password" name="_gb_backup_smtp_pass" class="gb-input" value="" autocomplete="new-password" placeholder="<?php echo $gb_backup && $gb_backup['password'] !== '' ? 'Saved password present — leave blank to keep it' : 'The mailbox password'; ?>">
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Outgoing Server</label>
                            <input type="text" name="_gb_backup_smtp_host" class="gb-input" value="<?php echo esc_attr($gb_backup ? $gb_backup['host'] : 'smtp.livemail.co.uk'); ?>" placeholder="smtp.livemail.co.uk">
                        </div>
                    </div>
                    <div class="gb-link-row" style="margin-bottom:8px;">
                        <div class="gb-form-row">
                            <label class="gb-label">Port</label>
                            <input type="number" name="_gb_backup_smtp_port" class="gb-input" value="<?php echo esc_attr($gb_backup ? (string) $gb_backup['port'] : '587'); ?>" placeholder="587">
                        </div>
                        <div class="gb-form-row">
                            <label class="gb-label">Encryption</label>
                            <select name="_gb_backup_smtp_secure" class="gb-input" style="height:45px;">
                                <option value="tls" <?php selected($gb_backup ? $gb_backup['secure'] : 'tls', 'tls'); ?>>STARTTLS (port 587)</option>
                                <option value="ssl" <?php selected($gb_backup ? $gb_backup['secure'] : 'tls', 'ssl'); ?>>SSL (port 465)</option>
                                <option value="none" <?php selected($gb_backup ? $gb_backup['secure'] : 'tls', 'none'); ?>>None (not recommended)</option>
                            </select>
                        </div>
                    </div>
                    <p style="margin:0 0 26px; color:#64748b; font-size:12px;">Save, then press <strong>Test backup sign-in</strong> at the top. It signs in without sending anything, and the site repeats that check twice a day.</p>

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

                    <?php $gb_log_rows = function_exists('gb_outbox_recent') ? gb_outbox_recent(30) : []; ?>
                    <p class="gb-mail-sub">Recent emails
                        <small>The last 30 emails the site sent, which route delivered each one, and why any failed. Anything that could not be delivered can be sent again from here. Kept for 30 days.</small>
                    </p>
                    <?php if (empty($gb_log_rows)): ?>
                        <p style="margin:0; color:#64748b; font-size:13px;">Nothing logged yet. Emails appear here from the moment they are sent.</p>
                    <?php else: ?>
                        <div class="gb-mail-log-wrap">
                            <table class="gb-mail-log">
                                <thead><tr><th>When</th><th>Email</th><th>Result</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($gb_log_rows as $gb_row):
                                    $gb_state = (string) $gb_row->status;
                                    $gb_route_name = function_exists('gb_outbox_route_label') ? gb_outbox_route_label((string) $gb_row->route) : (string) $gb_row->route;
                                    switch ($gb_state) {
                                        case 'sent':
                                            $gb_pill = ($gb_row->route === 'brevo' && (string) ($gb_row->delivery ?? '') === 'blocked')
                                                ? ['bad', 'Blocked by Brevo']
                                                : [$gb_row->route === 'php_mail' ? 'warn' : 'good', 'Sent by ' . $gb_route_name];
                                            break;
                                        case 'retrying':
                                            $gb_pill = ['warn', 'Retrying ' . ($gb_row->next_attempt_at ? 'at ' . get_date_from_gmt($gb_row->next_attempt_at, 'H:i') : 'soon')];
                                            break;
                                        case 'dead':
                                            $gb_pill = ['bad', 'Not delivered'];
                                            break;
                                        case 'bounced':
                                            $gb_pill = ['bad', 'Bounced: address not found'];
                                            break;
                                        case 'superseded':
                                            $gb_pill = ['idle', 'Replaced by a newer one'];
                                            break;
                                        case 'dismissed':
                                            $gb_pill = ['idle', 'Cleared'];
                                            break;
                                        default:
                                            $gb_pill = ['idle', 'Sending'];
                                    }
                                    /*
                                     * "Sending" means another request is sending it right now, and
                                     * resending it then is how a customer gets two copies. Only once
                                     * it has been stuck there long enough that the send must have
                                     * died (the same test the queue uses) is it offered again.
                                     */
                                    $gb_stuck = $gb_state === 'sending' && function_exists('gb_outbox_is_stuck') && gb_outbox_is_stuck($gb_row);
                                    if ($gb_stuck) {
                                        $gb_pill = ['warn', 'Stuck while sending'];
                                    }
                                    $gb_can_resend = !empty($gb_row->resendable) && (in_array($gb_state, ['retrying', 'dead'], true) || $gb_stuck);
                                    ?>
                                    <tr>
                                        <td style="white-space:nowrap; color:#64748b;"><?php echo esc_html(get_date_from_gmt($gb_row->created_at, 'j M, H:i')); ?></td>
                                        <td>
                                            <div class="gb-mail-log__subject"><?php echo esc_html($gb_row->subject !== '' ? $gb_row->subject : '(no subject)'); ?></div>
                                            <div style="color:#64748b;"><?php echo esc_html($gb_row->to_email); ?></div>
                                            <?php if (trim((string) $gb_row->last_error) !== '' && $gb_state !== 'superseded'): ?>
                                                <?php $gb_resolved = $gb_state === 'sent' && $gb_pill[0] !== 'bad'; ?>
                                                <div class="gb-mail-log__why<?php echo $gb_resolved ? ' is-resolved' : ''; ?>"><?php echo $gb_resolved ? 'Got through after: ' : ''; ?><?php echo esc_html((string) $gb_row->last_error); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <span class="gb-mail-pill is-<?php echo esc_attr($gb_pill[0]); ?>"><?php echo esc_html($gb_pill[1]); ?></span>
                                            <?php
                                            $gb_delivery_note = [
                                                'delivered' => 'Confirmed delivered',
                                                'delayed'   => 'Delayed; Brevo still trying',
                                                'spam'      => 'Recipient marked it as spam',
                                                'blocked'   => $gb_row->route === 'brevo' ? '' : 'Brevo blocked it, so it went this way',
                                            ][(string) ($gb_row->delivery ?? '')] ?? '';
                                            ?>
                                            <?php if ($gb_delivery_note !== ''): ?>
                                                <div style="margin-top:4px; font-size:11px; color:<?php echo $gb_row->delivery === 'delivered' ? '#166534' : '#b45309'; ?>;"><?php echo esc_html($gb_delivery_note); ?></div>
                                            <?php endif; ?>
                                            <?php if ((int) $gb_row->attempts > 1): ?>
                                                <div style="margin-top:4px; font-size:11px; color:#94a3b8;"><?php echo (int) $gb_row->attempts; ?> attempts</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $gb_is_blocked = (string) ($gb_row->delivery ?? '') === 'blocked'; ?>
                                            <?php if ($gb_can_resend || $gb_is_blocked || in_array($gb_state, ['retrying', 'dead', 'bounced'], true)): ?>
                                                <div class="gb-mail-log__btns">
                                                    <?php if ($gb_is_blocked): ?>
                                                        <button type="submit" form="gb-email-unblock-form" name="gb_email_log_id" value="<?php echo (int) $gb_row->id; ?>" class="gb-btn-small-os">Unblock in Brevo</button>
                                                    <?php endif; ?>
                                                    <?php if ($gb_can_resend): ?>
                                                        <button type="submit" form="gb-email-resend-form" name="gb_email_log_id" value="<?php echo (int) $gb_row->id; ?>" class="gb-btn-small-os">Resend now</button>
                                                    <?php endif; ?>
                                                    <button type="submit" form="gb-email-dismiss-form" name="gb_email_log_id" value="<?php echo (int) $gb_row->id; ?>" class="gb-btn-small-os">Clear</button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
                    <div style="margin:0 0 8px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Sharing Preview</div>
                    <p style="margin:0 0 12px; color:#64748b; font-size:12px;">The picture shown when someone shares a link to the site on Facebook, WhatsApp, LinkedIn or in a message. Use a wide photo, around 1200&times;630. Leave blank and the home page hero image is used.</p>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Default share image URL</label>
                        <input type="url" name="_gb_social_share_image" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_social_share_image', true)); ?>" placeholder="https://thegreenbroom.uk/wp-content/uploads/...">
                    </div>
                    <div style="margin:0 0 8px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Google Reviews</div>
                    <p style="margin:0 0 12px; color:#64748b; font-size:12px;">Install a reviews plugin (e.g. "Widgets for Google Reviews" by Trustindex), connect your Google Business profile, then paste its shortcode here to show reviews on the homepage. Leave blank to hide.</p>
                    <div class="gb-form-row" style="margin-bottom:0;">
                        <label class="gb-label">Google Reviews Shortcode / Embed</label>
                        <input type="text" name="_gb_reviews_embed" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_reviews_embed', true)); ?>" placeholder="[trustindex no-registration=google]">
                    </div>
                </div>
            </div>

            <div id="cookies" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-privacy"></span> Cookies &amp; Consent</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 20px; padding:12px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; color:#166534; font-size:13px; line-height:1.6;">
                        The banner appears once on a visitor's first page, remembers the answer for six months, and stays hidden after that.
                        Sign-in and security cookies are always allowed &mdash; the law does not require consent for those, so the banner does not pretend to ask.
                        The Analytics and Marketing switches are there so that the moment you add Google Analytics or a Meta pixel, the visitor's answer is already on file and honoured.
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Show the banner</label>
                        <select name="_gb_cookie_bar_enabled" class="gb-input" style="height:45px;">
                            <option value="1" <?php selected(get_post_meta($settings_id, '_gb_cookie_bar_enabled', true) !== '0'); ?>>Yes &mdash; show it to new visitors</option>
                            <option value="0" <?php selected(get_post_meta($settings_id, '_gb_cookie_bar_enabled', true), '0'); ?>>No &mdash; hide it completely</option>
                        </select>
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Heading</label>
                        <input type="text" name="_gb_cookie_bar_title" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_cookie_bar_title', true)); ?>" placeholder="We use cookies">
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Leave blank to use &ldquo;We use cookies&rdquo;.</p>
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Message</label>
                        <textarea name="_gb_cookie_bar_body" class="gb-input" rows="3" placeholder="We use essential cookies to keep the site working and you signed in. With your permission we would also like to measure how the site is used so we can improve it."><?php echo esc_textarea(get_post_meta($settings_id, '_gb_cookie_bar_body', true)); ?></textarea>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Keep it to a sentence or two. Leave blank for the default wording.</p>
                    </div>
                    <div class="gb-link-row" style="margin-bottom:20px;">
                        <div class="gb-form-row"><label class="gb-label">&ldquo;Accept&rdquo; button</label><input type="text" name="_gb_cookie_bar_accept" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_cookie_bar_accept', true)); ?>" placeholder="Accept all"></div>
                        <div class="gb-form-row"><label class="gb-label">&ldquo;Reject&rdquo; button</label><input type="text" name="_gb_cookie_bar_reject" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_cookie_bar_reject', true)); ?>" placeholder="Essential only"></div>
                    </div>
                    <div class="gb-link-row" style="margin-bottom:0;">
                        <div class="gb-form-row"><label class="gb-label">&ldquo;Choose&rdquo; link</label><input type="text" name="_gb_cookie_bar_choose" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_cookie_bar_choose', true)); ?>" placeholder="Choose"></div>
                        <div class="gb-form-row"><label class="gb-label">Cookie policy link</label><input type="url" name="_gb_cookie_policy_url" class="gb-input" value="<?php echo esc_attr(get_post_meta($settings_id, '_gb_cookie_policy_url', true)); ?>" placeholder="<?php echo esc_attr(home_url('/cookies/')); ?>"><p style="margin:8px 0 0; color:#64748b; font-size:12px;">Adds a &ldquo;Read more&rdquo; link. Defaults to your Cookie Policy page.</p></div>
                    </div>
                </div>
            </div>

            <div id="retention" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-backup"></span> Data Retention</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 20px; padding:12px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; color:#166534; font-size:13px; line-height:1.6;">
                        A cleaner&rsquo;s ID and DBS certificate are destroyed the moment they are approved &mdash; those files are never kept.
                        What remains is the audit trail: what was checked, by whom, when, and a fingerprint of each document seen. This setting governs how long that trail is kept.
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Keep the verification audit trail for</label>
                        <select name="_gb_audit_retention_years" class="gb-input" style="height:45px;">
                            <?php $gb_ret = function_exists('gb_get_audit_retention_years') ? gb_get_audit_retention_years() : 6; ?>
                            <?php foreach ([3, 5, 6, 7, 10] as $gb_y): ?>
                                <option value="<?php echo esc_attr($gb_y); ?>" <?php selected($gb_ret, $gb_y); ?>>
                                    <?php echo esc_html($gb_y); ?> years<?php echo $gb_y === 6 ? ' (recommended)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Six years matches the limitation period for a contract claim in England and Wales &mdash; the outside window in which you could still be asked to account for a verification decision.
                        </p>
                    </div>
                    <div style="padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#475569; font-size:12.5px; line-height:1.6;">
                        <strong>When the clock starts.</strong> Not when each entry is written, but when the cleaner leaves &mdash; measured from their last recorded activity once their profile is taken down.
                        While someone is still listed, the record of why they were verified is still doing its job, and there is no obligation to delete something you currently need.
                        When a trail is erased, a dated note is left in its place recording that it happened and what period it covered, so an erasure is never mistaken for a gap.
                    </div>
                </div>
            </div>

            <div id="membership" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-privacy"></span> Customer Membership</div>
                <div class="gb-editor-body">
                    <div style="margin:0 0 20px; padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1d4ed8; font-size:13px; line-height:1.6;">
                        Browsing cleaners is always open, because few people subscribe before seeing who is available. Membership is what lets
                        somebody <strong>book or message</strong> a cleaner. Memberships are managed under
                        <a href="?tab=members" style="color:#1d4ed8; font-weight:700;">Members</a>; <strong>nothing is restricted while this is switched off</strong>,
                        and it should stay off until you can actually take payment.
                    </div>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Require a membership to book or message cleaners</label>
                        <select name="_gb_membership_gate" class="gb-input" style="height:45px;">
                            <option value="0" <?php selected(get_post_meta($settings_id, '_gb_membership_gate', true) !== '1'); ?>>No &mdash; anyone can browse, book and message</option>
                            <option value="1" <?php selected(get_post_meta($settings_id, '_gb_membership_gate', true), '1'); ?>>Yes &mdash; anyone can browse; booking and messaging are for members</option>
                        </select>
                        <?php $gb_gate_problems = (function_exists('gb_membership_gate_problems') && get_post_meta($settings_id, '_gb_membership_gate', true) === '1') ? gb_membership_gate_problems() : []; ?>
                        <?php if ($gb_gate_problems): ?>
                            <div style="margin:10px 0 0; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; font-size:13px; line-height:1.6;">
                                <strong>The gate is on, but nobody can become a member right now.</strong> Non-members cannot book or message anyone until this is fixed:
                                <?php foreach ($gb_gate_problems as $gb_gp): ?><br>&bull; <?php echo esc_html($gb_gp); ?><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Either way, every visitor can search and read cleaner profiles. With this on, Book Now and Message Professional are
                            replaced for non-members by a <strong>Become a member</strong> button, and the booking page and messaging refuse them too.
                        </p>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Do not switch this on before Stripe is connected. With no way to buy a membership, turning it on would lock out every customer
                            and leave them no route back in.
                        </p>
                    </div>
                    <?php
                    /*
                     * The cleaner side of the same switch. A cleaner's listing
                     * subscription was only ever displayed, so somebody who
                     * cancelled, or never paid, stayed listed and bookable.
                     * With this on, only cleaners whose listing is paid or on
                     * its free months in the CURRENT Stripe mode are shown and
                     * can be booked (inc/identity-guard.php). Off until go-live,
                     * like the gate above: switched on with no live listings
                     * yet, it would empty Find a Cleaner.
                     */
                    $gb_lgate_on      = get_post_meta($settings_id, '_gb_listing_gate', true) === '1';
                    $gb_lgate_summary = function_exists('gb_listing_gate_summary') ? gb_listing_gate_summary() : null;
                    ?>
                    <div class="gb-form-row" style="margin-bottom:22px;">
                        <label class="gb-label">Require a paid cleaner listing to appear and take bookings</label>
                        <select name="_gb_listing_gate" class="gb-input" style="height:45px;">
                            <option value="0" <?php selected(!$gb_lgate_on); ?>>No: every live cleaner is shown and can be booked</option>
                            <option value="1" <?php selected($gb_lgate_on); ?>>Yes: only cleaners with a paid or free trial listing are shown and can be booked</option>
                        </select>
                        <?php if ($gb_lgate_summary): ?>
                            <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                                Right now <strong><?php echo (int) $gb_lgate_summary['shown']; ?> of <?php echo (int) $gb_lgate_summary['live']; ?></strong> live cleaners have a listing that counts in
                                <?php echo esc_html(function_exists('gb_stripe_site_mode') && gb_stripe_site_mode() === 'live' ? 'live' : 'test'); ?> mode<?php echo $gb_lgate_on ? '' : ', so that is how many would stay on show if you switched this on'; ?>.
                                Subscriptions made in test mode do not count once Stripe is live.
                            </p>
                            <?php if ($gb_lgate_on && !$gb_lgate_summary['can_buy']): ?>
                                <div style="margin:10px 0 0; padding:12px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b; font-size:13px; line-height:1.6;">
                                    <strong>This is on, but cleaners cannot buy a listing right now.</strong> The cleaner plan is not set up in Stripe for this mode,
                                    so nobody new can appear until it is. Set it up under Membership plans, or switch this off.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Managers always see every cleaner. A cleaner can still see their own profile, and you can give somebody a free listing from their record under Cleaners.
                        </p>
                    </div>
                    <div style="padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#475569; font-size:12.5px; line-height:1.6;">
                        <strong>What happens when Stripe arrives.</strong> Billing writes to the same membership record the Members screen already uses,
                        so the only remaining step is turning this switch on. Managers and administrators always keep access, and anyone whose membership
                        has lapsed is treated as expired the moment the date passes, not overnight.
                    </div>
                </div>
            </div>

            <div id="pricing" class="gb-editor-section gb-settings-panel">
                <div class="gb-editor-header"><span class="dashicons dashicons-money-alt"></span> Membership Pricing Panels</div>
                <div class="gb-editor-body">
                    <?php
                    /*
                     * One set of figures, three places on the public site. The
                     * customer panel appears on the homepage and on Find a
                     * Cleaner; the cleaner panel on Become a Cleaner. Editing a
                     * price here changes it everywhere at once.
                     */
                    $gb_price = static function ($key) {
                        return function_exists('gb_pricing_field') ? gb_pricing_field($key) : '';
                    };
                    ?>
                    <div style="margin:0 0 20px; padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1d4ed8; font-size:13px; line-height:1.6;">
                        These panels state the prices and send people to registration. <strong>Nothing is charged and nothing is locked</strong> &mdash; there is no checkout until Stripe is connected, so you follow up with new sign-ups yourself. When Stripe arrives, only the button changes.
                    </div>

                    <div class="gb-sub-head">Customer membership &mdash; homepage and Find a Cleaner</div>
                    <div class="gb-form-row" style="margin-bottom:14px;">
                        <label class="gb-label">Show this panel</label>
                        <select name="_gb_price_cust_show" class="gb-input" style="height:45px;">
                            <option value="1" <?php selected((string) $gb_price('_gb_price_cust_show'), '1'); ?>>Yes</option>
                            <option value="0" <?php selected((string) $gb_price('_gb_price_cust_show'), '0'); ?>>No &mdash; hide it everywhere</option>
                        </select>
                    </div>
                    <div class="gb-form-row"><label class="gb-label">Heading</label><input type="text" name="_gb_price_cust_head" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_head')); ?>"></div>
                    <div class="gb-form-row"><label class="gb-label">Sub-heading</label><textarea name="_gb_price_cust_sub" class="gb-textarea"><?php echo esc_textarea($gb_price('_gb_price_cust_sub')); ?></textarea></div>
                    <div class="gb-form-row">
                        <label class="gb-label">What members get (one per line)</label>
                        <textarea name="_gb_price_cust_benefits" class="gb-textarea" rows="6"><?php echo esc_textarea($gb_price('_gb_price_cust_benefits')); ?></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div><label class="gb-label">Plan 1 name</label><input type="text" name="_gb_price_cust_p1_name" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p1_name')); ?>"></div>
                        <div><label class="gb-label">Plan 1 price</label><input type="text" name="_gb_price_cust_p1_price" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p1_price')); ?>"></div>
                        <div><label class="gb-label">Plan 1 period</label><input type="text" name="_gb_price_cust_p1_period" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p1_period')); ?>"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div><label class="gb-label">Plan 2 name</label><input type="text" name="_gb_price_cust_p2_name" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p2_name')); ?>"></div>
                        <div><label class="gb-label">Plan 2 price</label><input type="text" name="_gb_price_cust_p2_price" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p2_price')); ?>"></div>
                        <div><label class="gb-label">Plan 2 period</label><input type="text" name="_gb_price_cust_p2_period" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p2_period')); ?>"></div>
                        <div><label class="gb-label">Plan 2 flash</label><input type="text" name="_gb_price_cust_p2_flag" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_p2_flag')); ?>"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div><label class="gb-label">Button text</label><input type="text" name="_gb_price_cust_btn" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_btn')); ?>"></div>
                        <div><label class="gb-label">Button link</label><input type="text" name="_gb_price_cust_link" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_cust_link')); ?>"></div>
                    </div>
                    <div class="gb-form-row" style="margin-bottom:28px;">
                        <label class="gb-label">Small print under the button</label>
                        <textarea name="_gb_price_cust_foot" class="gb-textarea"><?php echo esc_textarea($gb_price('_gb_price_cust_foot')); ?></textarea>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Shown while the panel is not selling &mdash; this is where you say nothing is being charged yet.</p>
                    </div>
                    <div class="gb-form-row" style="margin-bottom:28px;">
                        <label class="gb-label">Small print once payments are live</label>
                        <textarea name="_gb_price_cust_foot_live" class="gb-textarea"><?php echo esc_textarea($gb_price('_gb_price_cust_foot_live')); ?></textarea>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Swaps in automatically when the buy buttons appear, so the wording and the buttons can never disagree.</p>
                    </div>

                    <div class="gb-sub-head">Cleaner membership &mdash; Become a Cleaner</div>
                    <div class="gb-form-row" style="margin-bottom:14px;">
                        <label class="gb-label">Show this panel</label>
                        <select name="_gb_price_clean_show" class="gb-input" style="height:45px;">
                            <option value="1" <?php selected((string) $gb_price('_gb_price_clean_show'), '1'); ?>>Yes</option>
                            <option value="0" <?php selected((string) $gb_price('_gb_price_clean_show'), '0'); ?>>No &mdash; hide it</option>
                        </select>
                    </div>
                    <div class="gb-form-row"><label class="gb-label">Heading</label><input type="text" name="_gb_price_clean_head" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_clean_head')); ?>"></div>
                    <div class="gb-form-row"><label class="gb-label">Sub-heading</label><textarea name="_gb_price_clean_sub" class="gb-textarea"><?php echo esc_textarea($gb_price('_gb_price_clean_sub')); ?></textarea></div>
                    <div class="gb-form-row">
                        <label class="gb-label">What cleaners get (one per line)</label>
                        <textarea name="_gb_price_clean_benefits" class="gb-textarea" rows="6"><?php echo esc_textarea($gb_price('_gb_price_clean_benefits')); ?></textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div><label class="gb-label">Free trial flash</label><input type="text" name="_gb_price_clean_free" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_clean_free')); ?>"></div>
                        <div><label class="gb-label">Price after the trial</label><input type="text" name="_gb_price_clean_price" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_clean_price')); ?>"></div>
                        <div><label class="gb-label">Period</label><input type="text" name="_gb_price_clean_period" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_clean_period')); ?>"></div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div><label class="gb-label">Button text</label><input type="text" name="_gb_price_clean_btn" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_clean_btn')); ?>"></div>
                        <div><label class="gb-label">Button link</label><input type="text" name="_gb_price_clean_link" class="gb-input" value="<?php echo esc_attr($gb_price('_gb_price_clean_link')); ?>"></div>
                    </div>
                    <div class="gb-form-row">
                        <label class="gb-label">Small print under the button</label>
                        <textarea name="_gb_price_clean_foot" class="gb-textarea"><?php echo esc_textarea($gb_price('_gb_price_clean_foot')); ?></textarea>
                    </div>
                    <div class="gb-form-row">
                        <label class="gb-label">Small print once payments are live</label>
                        <textarea name="_gb_price_clean_foot_live" class="gb-textarea"><?php echo esc_textarea($gb_price('_gb_price_clean_foot_live')); ?></textarea>
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
                <div class="gb-editor-header"><span class="dashicons dashicons-shield"></span> Stripe Credentials</div>
                <div class="gb-editor-body">
                    <?php
                    /*
                     * The mode was its own settings section, one dropdown away
                     * from the two columns of keys it chooses between. It picks
                     * which column is live, so it belongs at the top of them.
                     */
                    ?>
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
                    <?php
                    /*
                     * Payment switches (inc/payment-switches.php). A hidden "0"
                     * before each checkbox so that unticking actually saves as
                     * off; the generic _gb_ saver stores whichever arrives last.
                     */
                    $gb_switches = array(
                        array('_gb_pause_bookings', 'Pause online bookings', 'Nobody can book or pay for a clean. Listings and profiles stay up and say booking is paused. Use it for holidays, or if something looks wrong.', function_exists('gb_bookings_paused') && gb_bookings_paused(), 'warn'),
                        array('_gb_pause_memberships', 'Pause new memberships', 'Hides the buy buttons for customer memberships and cleaner listings ("Sign-ups paused"). Existing members carry on and can still manage or cancel.', function_exists('gb_memberships_paused') && gb_memberships_paused(), 'warn'),
                        array('_gb_auto_refunds', 'Automatic refunds on cancellation', 'On: a cancelled booking is refunded straight away, by the cancellation policy. Off: the refund due is recorded on the booking and emailed to the team to send by hand.', !function_exists('gb_auto_refunds_enabled') || gb_auto_refunds_enabled(), 'ok'),
                    );
                    ?>
                    <div class="gb-switches" style="margin:22px 0 0;">
                        <label class="gb-label" style="margin-bottom:10px;">Payment switches</label>
                        <?php foreach ($gb_switches as $gb_sw): list($gb_sw_key, $gb_sw_label, $gb_sw_help, $gb_sw_on, $gb_sw_tone) = $gb_sw; ?>
                            <label class="gb-switch-row">
                                <span class="gb-switch-text">
                                    <strong><?php echo esc_html($gb_sw_label); ?></strong>
                                    <span><?php echo esc_html($gb_sw_help); ?></span>
                                </span>
                                <input type="hidden" name="<?php echo esc_attr($gb_sw_key); ?>" value="0">
                                <input type="checkbox" class="gb-switch-input" name="<?php echo esc_attr($gb_sw_key); ?>" value="1" <?php checked($gb_sw_on); ?>>
                                <span class="gb-switch<?php echo $gb_sw_tone === 'warn' ? ' gb-switch-warn' : ''; ?>" aria-hidden="true"></span>
                            </label>
                        <?php endforeach; ?>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">Changes take effect when you press <strong>Update Settings</strong>.</p>
                    </div>
                    <hr style="border:none; border-top:1px solid #e2e8f0; margin:22px 0;">

                    <?php if (!empty($stripe_missing)): ?>
                        <div style="margin:0 0 14px; padding:10px 12px; border:1px solid #fecaca; border-radius:8px; background:#fef2f2; color:#991b1b; font-size:13px;">
                            Stripe setup incomplete for current mode. Fill in: <?php echo esc_html(implode(', ', $stripe_missing)); ?>.
                        </div>
                    <?php else: ?>
                        <?php if (!empty($stripe_connect_missing)): ?>
                            <div style="margin:0 0 14px; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#475569; font-size:12.5px; line-height:1.6;">
                                The Connect webhook secret is not set. It is only read once cleaners are being paid out, and it comes from a second webhook endpoint in Stripe rather than the one above.
                                <br><br>
                                There are three because Stripe will not let one endpoint carry both its old and new event formats. The main endpoint takes payments and subscriptions, a second takes connected-account events, and a third takes the Accounts v2 events. Each has its own signing secret, and the site tries all three against every message it receives.
                            </div>
                        <?php endif; ?>
                        <div style="margin:0 0 14px; padding:10px 12px; border:1px solid #dcfce7; border-radius:8px; background:#f0fdf4; color:#166534; font-size:13px;">
                            Stripe credentials look complete for the active mode.
                        </div>
                    <?php endif; ?>
                    <p style="margin:0 0 18px; color:#64748b; font-size:13px;">
                        Active mode above determines which key set is used in booking/payment flows.
                    </p>
                    <?php
                    /*
                     * Keys and secrets folded away. Once set, nobody needs to see two
                     * columns of ten password boxes every time they open Settings; each
                     * group says in its heading whether it is complete, and opens itself
                     * when the active mode is missing something. Opening one closes the
                     * others (the details name attribute, with a small script fallback).
                     * Closed groups still submit with the form: blank secrets mean keep.
                     */
                    $gb_key_state = static function ($mode) use ($settings_id) {
                        $fields = array('publishable_key', 'secret_key', 'webhook_secret', 'connect_webhook_secret');
                        $missing = 0;
                        foreach ($fields as $f) {
                            if (trim((string) get_post_meta($settings_id, '_gb_stripe_' . $mode . '_' . $f, true)) === '') {
                                $missing++;
                            }
                        }
                        return $missing;
                    };
                    $gb_acc_badge = static function ($missing) {
                        return $missing === 0
                            ? '<span class="gb-acc-badge is-ok">Complete</span>'
                            : '<span class="gb-acc-badge is-warn">' . (int) $missing . ' missing</span>';
                    };
                    $gb_live_missing = $gb_key_state('live');
                    $gb_test_missing = $gb_key_state('test');
                    ?>
                    <details class="gb-acc" name="gb-stripe-keys" data-acc-group="gb-stripe-keys"<?php echo ($stripe_mode === 'live' && $gb_live_missing) ? ' open' : ''; ?>>
                        <summary><span class="gb-acc-title">Live keys &amp; webhook secrets<?php echo $stripe_mode === 'live' ? ' <em>in use</em>' : ''; ?></span><?php echo $gb_acc_badge($gb_live_missing); ?></summary>
                        <div class="gb-acc-body">
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
                        <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                            <label class="gb-label">Live Accounts v2 Webhook Secret</label>
                            <input type="password" name="_gb_stripe_live_v2_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_live_v2_webhook_secret', true) ? 'Saved — leave blank to keep' : 'whsec_...'); ?>" autocomplete="new-password">
                        </div>
                        </div>
                    </details>
                    <details class="gb-acc" name="gb-stripe-keys" data-acc-group="gb-stripe-keys"<?php echo ($stripe_mode === 'test' && $gb_test_missing) ? ' open' : ''; ?>>
                        <summary><span class="gb-acc-title">Test (sandbox) keys &amp; webhook secrets<?php echo $stripe_mode === 'test' ? ' <em>in use</em>' : ''; ?></span><?php echo $gb_acc_badge($gb_test_missing); ?></summary>
                        <div class="gb-acc-body">
                    <div style="margin:0 0 18px; padding:12px 14px; border:1px dashed #cbd5e1; border-radius:8px; color:#475569; font-size:13px;">
                        Sandbox checklist: keep the platform in <strong>Test Mode</strong>, paste the test publishable and secret keys plus both webhook secrets, use the cleaner onboarding buttons in <strong>Manage Cleaners</strong>, then run a sample checkout below or from a live booking flow. When you are satisfied, switch to <strong>Live Mode</strong> and add the live keys.
                    </div>
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
                        <div class="gb-form-row" style="margin-top:12px; margin-bottom:0;">
                            <label class="gb-label">Test Accounts v2 Webhook Secret</label>
                            <input type="password" name="_gb_stripe_test_v2_webhook_secret" class="gb-input" value="" placeholder="<?php echo esc_attr(get_post_meta($settings_id, '_gb_stripe_test_v2_webhook_secret', true) ? 'Saved — leave blank to keep' : 'whsec_...'); ?>" autocomplete="new-password">
                        </div>
                        </div>
                    </details>
                    <details class="gb-acc" name="gb-stripe-keys" data-acc-group="gb-stripe-keys">
                        <summary><span class="gb-acc-title">Webhook address for Stripe</span></summary>
                        <div class="gb-acc-body">
                    <div class="gb-form-row" style="margin-bottom:20px;">
                        <label class="gb-label">Stripe Webhook Endpoint URL</label>
                        <input type="text" class="gb-input" value="<?php echo esc_attr(function_exists('gb_get_stripe_webhook_url') ? gb_get_stripe_webhook_url() : admin_url('admin-post.php?action=gb_stripe_webhook')); ?>" readonly>
                        <p style="margin:8px 0 0; color:#64748b; font-size:12px;">
                            Add this URL in your Stripe Dashboard webhook settings for the active environment.
                        </p>
                    </div>
                        </div>
                    </details>
                    <details class="gb-acc" name="gb-stripe-keys" data-acc-group="gb-stripe-keys">
                        <summary><span class="gb-acc-title">Legacy fallback keys <em>not normally used</em></span></summary>
                        <div class="gb-acc-body">
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
                    </details>
                </div>
            </div>
        </form>
        <?php
        /*
         * The Resend and Clear buttons in the email log sit inside the
         * settings form, and forms cannot nest, so they submit these two by
         * their form attribute instead.
         */
        ?>
        <form method="post" id="gb-email-resend-form" action="<?php echo esc_url(home_url('/manager-dashboard/')); ?>" hidden>
            <input type="hidden" name="gb_save_action" value="resend_email">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce', true, true); ?>
        </form>
        <form method="post" id="gb-email-unblock-form" action="<?php echo esc_url(home_url('/manager-dashboard/')); ?>" hidden>
            <input type="hidden" name="gb_save_action" value="unblock_email">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce', true, true); ?>
        </form>
        <form method="post" id="gb-email-dismiss-form" action="<?php echo esc_url(home_url('/manager-dashboard/')); ?>" hidden>
            <input type="hidden" name="gb_save_action" value="dismiss_email">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce', true, true); ?>
        </form>

        <div id="stripe-plans" class="gb-editor-section gb-settings-panel">
            <div class="gb-editor-header"><span class="dashicons dashicons-tickets-alt"></span> Membership Plans in Stripe</div>
            <div class="gb-editor-body">
                <?php if ($stripe_notice !== '' && $stripe_msg !== ''): ?>
                    <div style="margin:0 0 14px; padding:10px 12px; border:1px solid <?php echo $stripe_notice === 'ok' ? '#dcfce7' : '#fecaca'; ?>; border-radius:8px; background:<?php echo $stripe_notice === 'ok' ? '#f0fdf4' : '#fef2f2'; ?>; color:<?php echo $stripe_notice === 'ok' ? '#166534' : '#991b1b'; ?>; font-size:13px;">
                        <?php echo esc_html($stripe_msg); ?>
                    </div>
                <?php endif; ?>
                <?php
                $gb_subs_mode   = get_post_meta($settings_id, '_gb_stripe_mode', true) ?: 'test';
                $gb_subs_report = function_exists('gb_get_subscription_report') ? gb_get_subscription_report() : array();
                $gb_subs_cat    = function_exists('gb_subscription_catalogue') ? gb_subscription_catalogue() : array();
                ?>
                <div style="margin:0 0 20px; padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1d4ed8; font-size:13px; line-height:1.6;">
                    The prices below come straight from the panels on the public site. Change one under <strong>Membership pricing</strong> and a button appears here to update Stripe to match. Anyone already paying stays on the price they signed up at.
                </div>

                <?php
                /* A status badge, not coloured text: bold green "Set up" read
                   like a link to press. The Stripe price id is still here for
                   support, but quiet, on hover. */
                $gb_pill = static function ($label, $bg, $fg, $title = '') {
                    return '<span' . ($title !== '' ? ' title="' . esc_attr($title) . '"' : '') . ' style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:' . $bg . '; color:' . $fg . '; font-size:11.5px; font-weight:700; white-space:nowrap; cursor:default;"><span aria-hidden="true" style="width:6px; height:6px; border-radius:50%; background:currentColor;"></span>' . esc_html($label) . '</span>';
                };
                ?>
                <?php $gb_needs_update = false; $gb_any_live = false; ?>
                <table class="gb-booking-table" style="margin-bottom:18px;">
                    <thead><tr><th>Plan</th><th>Charges</th><th>Every</th><th>Free trial</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($gb_subs_cat as $gb_pk => $gb_plan):
                        $gb_rec  = function_exists('gb_get_subscription_record') ? gb_get_subscription_record($gb_pk) : array();
                        $gb_row  = isset($gb_subs_report['plans'][$gb_pk]) ? $gb_subs_report['plans'][$gb_pk] : array();
                        $gb_live = !empty($gb_rec['price_id']);
                        $gb_amt  = function_exists('gb_subscription_plan_amount') ? gb_subscription_plan_amount($gb_pk) : null;
                        $gb_amt_err = is_wp_error($gb_amt) ? $gb_amt->get_error_message() : '';
                        // Checkout refuses a plan whose panel price no longer
                        // matches what Stripe will charge (inc/membership-checkout.php),
                        // so a changed price has to be pushed to Stripe before it sells.
                        $gb_stale = $gb_live && $gb_amt_err === '' && isset($gb_rec['amount_minor'])
                            && (int) $gb_rec['amount_minor'] !== (int) $gb_amt;
                        $gb_any_live = $gb_any_live || $gb_live;
                        if (!$gb_live || $gb_stale || (!empty($gb_row) && empty($gb_row['ok']))) {
                            $gb_needs_update = true;
                        }
                        ?>
                        <tr>
                            <td><strong style="font-size:13px; color:#0f172a;"><?php echo esc_html($gb_plan['label']); ?></strong></td>
                            <td style="font-size:12.5px; color:#475569; white-space:nowrap;">
                                <?php if ($gb_amt_err === ''): ?>
                                    <?php echo function_exists('gb_format_minor_amount') ? gb_format_minor_amount($gb_amt) : (int) $gb_amt; ?>
                                <?php else: ?>
                                    <span style="color:#b91c1c;">Cannot read the price</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12.5px; color:#475569; white-space:nowrap;"><?php echo (int) $gb_plan['interval_count'] === 1 ? esc_html($gb_plan['interval']) : esc_html($gb_plan['interval_count'] . ' ' . $gb_plan['interval'] . 's'); ?></td>
                            <td style="font-size:12.5px; color:#475569;"><?php echo $gb_plan['trial_days'] ? esc_html($gb_plan['trial_days'] . ' days') : '&mdash;'; ?></td>
                            <td style="font-size:12px;">
                                <?php if (!empty($gb_row) && empty($gb_row['ok'])): ?>
                                    <?php echo $gb_pill('Failed', '#fee2e2', '#b91c1c'); ?>
                                    <div style="color:#94a3b8; font-size:11px; margin-top:5px;"><?php echo esc_html($gb_row['error']); ?></div>
                                <?php elseif ($gb_stale): ?>
                                    <?php echo $gb_pill('Price changed: update Stripe', '#fef3c7', '#92400e', 'Stripe price ' . $gb_rec['price_id']); ?>
                                <?php elseif ($gb_live): ?>
                                    <?php echo $gb_pill('Ready in ' . ($gb_subs_mode === 'live' ? 'Live' : 'Test'), '#dcfce7', '#166534', 'Stripe price ' . $gb_rec['price_id']); ?>
                                <?php elseif ($gb_amt_err !== ''): ?>
                                    <?php echo $gb_pill('Blocked', '#fef3c7', '#92400e'); ?>
                                    <div style="color:#94a3b8; font-size:11px; margin-top:5px;"><?php echo esc_html($gb_amt_err); ?></div>
                                <?php else: ?>
                                    <?php echo $gb_pill('Not set up yet', '#f1f5f9', '#64748b'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($gb_subs_report['when'])): ?>
                    <p style="margin:0 0 14px; color:#94a3b8; font-size:12px;">
                        Last run <?php echo esc_html($gb_subs_report['when']); ?> in <strong><?php echo esc_html($gb_subs_mode); ?></strong> mode.
                        <?php if (!empty($gb_subs_report['error'])): ?>
                            <span style="color:#b91c1c;"><?php echo esc_html($gb_subs_report['error']); ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ($gb_needs_update): ?>
                    <?php /* Only offered when there is something to do: with every plan
                             ready it read as a step still waiting to be taken. */ ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="gb_stripe_provision_subscriptions">
                        <?php wp_nonce_field('gb_stripe_provision_subscriptions'); ?>
                        <button type="submit" class="gb-btn gb-btn-primary"><?php echo $gb_any_live ? 'Update the plans in Stripe' : 'Set up the plans in Stripe'; ?></button>
                        <p style="margin:10px 0 0; color:#64748b; font-size:12px;">
                            Until this is done, a plan marked above cannot be bought. It only touches the Stripe account for the current mode (<?php echo esc_html($gb_subs_mode); ?>).
                        </p>
                    </form>
                <?php else: ?>
                    <p style="margin:0; padding:10px 12px; border-radius:8px; background:#f0fdf4; border:1px solid #dcfce7; color:#166534; font-size:13px;">
                        All plans match Stripe. Nothing to do here.
                    </p>
                <?php endif; ?>
            </div>
        </div>

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
/* One open at a time in a settings group. The details "name" attribute does
   this natively in current browsers; this covers older ones. */
document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (!d || d.tagName !== 'DETAILS' || !d.open || !d.dataset.accGroup) { return; }
    document.querySelectorAll('details[data-acc-group="' + d.dataset.accGroup + '"]').forEach(function (o) {
        if (o !== d) { o.open = false; }
    });
}, true);
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
