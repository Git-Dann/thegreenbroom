<?php
/**
 * Customer memberships.
 *
 * Nothing is gated yet - the cleaner database is open to everyone. This screen
 * exists so memberships can be sold and recorded by hand before Stripe is
 * connected, and so the state is already real when billing arrives.
 */

$gb_gate_on = function_exists('gb_membership_gate_enabled') && gb_membership_gate_enabled();
$gb_plans   = function_exists('gb_membership_plans') ? gb_membership_plans() : [];

$gb_customers = get_users([
    'role__in' => ['gb_customer'],
    'number'   => 200,
    'orderby'  => 'registered',
    'order'    => 'DESC',
]);

/*
 * Anyone who has actually paid, whatever their role.
 *
 * This screen says "who has paid for access" and listed only users holding the
 * customer role, so a membership bought by a manager or an administrator was
 * invisible here - which is exactly what happened on the first live test and
 * made it look as though the payment had not recorded at all. A membership is
 * a membership whoever holds it.
 */
$gb_member_ids = array_map('intval', (array) get_users([
    'meta_key'     => GB_MEMBER_STATUS,
    'meta_compare' => 'EXISTS',
    'number'       => 200,
    'fields'       => 'ID',
]));

$gb_seen = [];
foreach ($gb_customers as $gb_u) {
    $gb_seen[(int) $gb_u->ID] = true;
}

foreach ($gb_member_ids as $gb_extra_id) {
    if (isset($gb_seen[$gb_extra_id])) {
        continue;
    }

    $gb_extra = get_userdata($gb_extra_id);

    // Only worth listing if there is something to show.
    if ($gb_extra && gb_get_membership($gb_extra_id)['status'] !== 'none') {
        $gb_customers[] = $gb_extra;
        $gb_seen[$gb_extra_id] = true;
    }
}

/*
 * Counted by display state, so Ending is its own number rather than being
 * buried inside Active. At a hundred members "how many are on their way out"
 * is the figure worth seeing, and it was not on the screen at all.
 */
$gb_counts = ['active' => 0, 'ending' => 0, 'expired' => 0, 'cancelled' => 0, 'none' => 0];
$gb_rows = [];
foreach ($gb_customers as $gb_u) {
    $gb_m     = gb_get_membership($gb_u->ID);
    $gb_state = gb_membership_display_state($gb_m);
    $gb_counts[$gb_state['key']] = ($gb_counts[$gb_state['key']] ?? 0) + 1;
    $gb_rows[] = [$gb_u, $gb_m, $gb_state];
}
?>

<div class="booking-controls" style="display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; align-items:flex-start;">
    <div>
        <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Customer Memberships</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Who has paid for access to the cleaner database.</p>
    </div>

    <?php
    /*
     * One sync for the screen, not one per row.
     *
     * "Is this list current?" is a question about the list. Thirty rows each
     * carrying their own status line and their own button is noise on a screen
     * whose whole job is to be read quickly.
     */
    $gb_last_sync = function_exists('gb_membership_last_sync') ? gb_membership_last_sync() : null;
    ?>
    <form method="post" style="text-align:right; margin:0;">
        <input type="hidden" name="gb_save_action" value="sync_all_memberships">
        <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
        <button type="submit" class="gb-btn-small-os" style="height:38px; padding:0 16px;">Sync from Stripe</button>
        <div style="margin-top:6px; font-size:11.5px; color:#94a3b8;">
            <?php if ($gb_last_sync): ?>
                Last synced
                <?php // The real time as the written value, so it is right with no JavaScript;
                      // the script below replaces it with "2 minutes ago" and keeps that current. ?>
                <time class="gb-since" datetime="<?php echo esc_attr(gmdate('c', (int) $gb_last_sync['at'])); ?>"
                      title="<?php echo esc_attr(date_i18n('j M Y, H:i', (int) $gb_last_sync['at'])); ?>"><?php echo esc_html(date_i18n('j M, H:i', (int) $gb_last_sync['at'])); ?></time>
            <?php else: ?>
                Never synced by hand &mdash; Stripe updates this on its own.
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
/*
 * Keeps "last synced" honest without reloading the page. A timestamp that
 * says "2 minutes ago" and still says it an hour later is worse than no
 * timestamp, because it is believed.
 */
(function () {
    var els = document.querySelectorAll('.gb-since');
    if (!els.length) { return; }

    function phrase(seconds) {
        if (seconds < 10)   { return 'just now'; }
        if (seconds < 60)   { return Math.floor(seconds) + ' seconds ago'; }
        var m = Math.floor(seconds / 60);
        if (m < 60)         { return m === 1 ? 'a minute ago' : m + ' minutes ago'; }
        var h = Math.floor(m / 60);
        if (h < 24)         { return h === 1 ? 'an hour ago' : h + ' hours ago'; }
        var d = Math.floor(h / 24);
        return d === 1 ? 'yesterday' : d + ' days ago';
    }

    function tick() {
        var now = Date.now();
        els.forEach(function (el) {
            var then = Date.parse(el.getAttribute('datetime'));
            if (isNaN(then)) { return; }
            el.textContent = phrase(Math.max(0, (now - then) / 1000));
        });
    }

    tick();
    setInterval(tick, 15000);
}());
</script>

<div style="margin:0 0 24px; padding:13px 15px; border-radius:10px; font-size:13px; line-height:1.6; <?php echo $gb_gate_on
    ? 'background:#fffbeb; border:1px solid #fde68a; color:#92400e;'
    : 'background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8;'; ?>">
    <?php if ($gb_gate_on): ?>
        <strong>The database is gated.</strong> Only customers with an active membership can browse cleaners.
    <?php else: ?>
        <strong>The database is open to everyone.</strong> Memberships can be recorded here, but nothing is restricted yet &mdash;
        browsing cleaners does not require one. Turn the gate on under <a href="?tab=settings&amp;section=membership" style="color:#1d4ed8; font-weight:700;">Settings &rarr; Membership</a> when you are ready.
    <?php endif; ?>
</div>

<?php
/*
 * gb-grid-stats, not gb-os-grid. The latter is the two-column editor layout -
 * a main column and a 340px sidebar - so four stat cards were being poured
 * into a shape built for two things, and its definition was not even loaded
 * on this tab, which is why they came out as a stack of bare text.
 */
?>
<?php
/*
 * The counts double as a filter. At a hundred members, reading a state off a
 * long table is the hard part, and "show me only the ones on their way out" is
 * the question this screen exists to answer. A link and a query string rather
 * than any JavaScript, so it survives a reload and can be sent to somebody.
 */
$gb_filter = isset($_GET['state']) ? sanitize_key(wp_unslash($_GET['state'])) : '';
if (!array_key_exists($gb_filter, $gb_counts)) {
    $gb_filter = '';
}
?>
<div class="gb-grid-stats" style="margin-bottom:24px;">
    <?php foreach ([
        'active'    => ['Active', '#16a34a'],
        'ending'    => ['Ending', '#d97706'],
        'expired'   => ['Expired', '#ea580c'],
        'cancelled' => ['Cancelled', '#64748b'],
        'none'      => ['No membership', '#94a3b8'],
    ] as $gb_k => $gb_cfg): ?>
        <?php $gb_is_on = $gb_filter === $gb_k; ?>
        <a class="gb-widget gb-widget-stat" style="display:block; text-decoration:none; <?php echo $gb_is_on ? 'border-color:' . esc_attr($gb_cfg[1]) . '; box-shadow:0 0 0 2px ' . esc_attr($gb_cfg[1]) . '22;' : ''; ?>"
           href="<?php echo esc_url(add_query_arg($gb_is_on ? ['tab' => 'members'] : ['tab' => 'members', 'state' => $gb_k], home_url('/manager-dashboard/'))); ?>"
           title="<?php echo $gb_is_on ? 'Show everybody again' : esc_attr('Show only: ' . $gb_cfg[0]); ?>">
            <div class="gb-widget-body">
                <div class="gb-stat-val" style="color:<?php echo esc_attr($gb_cfg[1]); ?>;"><?php echo (int) ($gb_counts[$gb_k] ?? 0); ?></div>
                <div class="gb-stat-label"><?php echo esc_html($gb_cfg[0]); ?><?php echo $gb_is_on ? ' &times;' : ''; ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($gb_filter !== ''): ?>
    <p style="margin:-10px 0 20px; font-size:13px; color:#475569;">
        Showing only <strong><?php echo esc_html($gb_filter); ?></strong>.
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'members'], home_url('/manager-dashboard/'))); ?>" style="color:#2e7d32; font-weight:700;">Show everybody</a>
    </p>
<?php endif; ?>

<?php
/*
 * Anybody stuck at the front door.
 *
 * A new account cannot get past the verification screen until it has clicked
 * a link in an email, so if email is not arriving they are simply stuck, with
 * no way in and nothing a manager can do about it. That is what happened to a
 * real signup today.
 *
 * Any role, not just customers: a cleaner signing up hits the same wall, and
 * this is the only screen where anybody was looking.
 */
$gb_unverified = get_users([
    'meta_key'     => 'gb_email_verified',
    'meta_value'   => '0',
    'number'       => 50,
    'orderby'      => 'registered',
    'order'        => 'DESC',
]);
?>
<?php if (!empty($gb_unverified)): ?>
    <div class="gb-card" style="padding:20px 22px; margin-bottom:24px; background:#fffbeb; border:1px solid #fde68a; border-radius:12px;">
        <div style="font-weight:800; color:#92400e; font-size:15px; margin-bottom:6px;">
            Waiting on email verification (<?php echo count($gb_unverified); ?>)
        </div>
        <p style="margin:0 0 16px; color:#92400e; font-size:13px; line-height:1.6;">
            These accounts cannot get in until they click the link in their verification email. If that email is not
            reaching them, verify them here and they can carry on straight away. Only do it for somebody you know is
            who they say they are.
        </p>
        <?php foreach ($gb_unverified as $gb_uv):
            $gb_uv_err = (string) get_user_meta($gb_uv->ID, 'gb_email_verification_last_error', true); ?>
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:12px 0; border-top:1px solid #fde68a;">
                <div style="min-width:0;">
                    <strong style="font-size:14px; color:#0f172a;"><?php echo esc_html($gb_uv->display_name); ?></strong>
                    <div style="font-size:11.5px; color:#92400e;"><?php echo esc_html($gb_uv->user_email); ?></div>
                    <?php if ($gb_uv_err !== ''): ?>
                        <div style="font-size:11px; color:#991b1b; margin-top:3px;">Last attempt: <?php echo esc_html($gb_uv_err); ?></div>
                    <?php endif; ?>
                </div>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="gb_save_action" value="verify_email_by_hand">
                    <input type="hidden" name="gb_member_user" value="<?php echo (int) $gb_uv->ID; ?>">
                    <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
                    <button type="submit" class="gb-btn-small-os"
                            onclick="return confirm('Verify <?php echo esc_attr($gb_uv->user_email); ?> by hand? Only do this if you know who they are.');">
                        Verify by hand
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="gb-card" style="padding:0; overflow:hidden; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
    <div class="gb-table-scroll">
        <table class="gb-booking-table gb-members-table">
            <thead><tr><th style="width:28%;">Customer</th><th>Status</th><th>Plan</th><th>Date</th><th style="text-align:right;">Set membership</th></tr></thead>
            <tbody>
            <?php
            $gb_visible = $gb_filter === ''
                ? $gb_rows
                : array_values(array_filter($gb_rows, static function ($gb_row) use ($gb_filter) {
                    return $gb_row[2]['key'] === $gb_filter;
                }));
            ?>
            <?php if (empty($gb_visible)): ?>
                <tr><td colspan="5" style="color:#64748b; font-size:13px;">
                    <?php echo $gb_filter === '' ? 'No customer accounts yet.' : 'Nobody is in that state right now.'; ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($gb_visible as [$gb_u, $gb_m, $gb_state]):
                $gb_colour   = $gb_state['colour'];
                $gb_row_date = gb_membership_display_date($gb_m); ?>
                <tr>
                    <td>
                        <strong style="font-size:14px; color:#0f172a;"><?php echo esc_html($gb_u->display_name); ?></strong>
                        <div style="font-size:11px; color:#94a3b8; margin-top:3px;"><?php echo esc_html($gb_u->user_email); ?></div>
                    </td>
                    <td>
                        <?php // One pill, one answer. Ending is a state here, not a note under Active. ?>
                        <span style="display:inline-block; min-width:92px; text-align:center; white-space:nowrap; padding:5px 11px; border-radius:20px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; background:<?php echo esc_attr($gb_colour); ?>1a; color:<?php echo esc_attr($gb_colour); ?>;">
                            <?php echo esc_html($gb_state['label']); ?>
                        </span>
                        <?php if ($gb_m['source'] === 'manual' && $gb_state['key'] !== 'none'): ?>
                            <div style="font-size:10.5px; color:#94a3b8; margin-top:4px;">Recorded by hand</div>
                        <?php endif; ?>
                        <?php
                        /*
                         * If their billing page refused to open, that is the
                         * only record of it. Without this the member says "the
                         * cancel button does nothing" and nobody here can see
                         * any more than they can.
                         */
                        $gb_bill_err = get_user_meta($gb_u->ID, '_gb_billing_last_error', true);
                        if (is_array($gb_bill_err) && !empty($gb_bill_err['message'])): ?>
                            <div style="font-size:10.5px; color:#991b1b; margin-top:4px;" title="<?php echo esc_attr($gb_bill_err['message']); ?>">
                                Billing page failed <?php echo !empty($gb_bill_err['at']) ? esc_html(date_i18n('j M, H:i', strtotime($gb_bill_err['at']))) : ''; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px; color:#475569;"><?php echo esc_html($gb_m['plan'] ? ($gb_plans[$gb_m['plan']]['label'] ?? $gb_m['plan']) : '&mdash;'); ?></td>
                    <?php // The date, and what that date means, rather than a bare number. ?>
                    <td style="font-size:12.5px; color:#475569; white-space:nowrap;">
                        <?php if ($gb_row_date !== '' && strtotime($gb_row_date)): ?>
                            <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:<?php echo esc_attr($gb_colour); ?>;"><?php echo esc_html($gb_state['date_label']); ?></div>
                            <?php echo esc_html(date_i18n('j M Y', strtotime($gb_row_date))); ?>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <form method="post" style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                            <input type="hidden" name="gb_save_action" value="save_membership">
                            <input type="hidden" name="gb_member_user" value="<?php echo (int) $gb_u->ID; ?>">
                            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
                            <select name="gb_member_status" class="gb-input gb-input-sm" style="min-width:128px;">
                                <?php foreach (['none' => 'Not a member', 'active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $gb_sv => $gb_sl): ?>
                                    <option value="<?php echo esc_attr($gb_sv); ?>" <?php selected($gb_m['status'], $gb_sv); ?>><?php echo esc_html($gb_sl); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="gb_member_plan" class="gb-input gb-input-sm" style="min-width:112px;">
                                <option value="">No plan</option>
                                <?php foreach ($gb_plans as $gb_pk => $gb_pv): ?>
                                    <option value="<?php echo esc_attr($gb_pk); ?>" <?php selected($gb_m['plan'], $gb_pk); ?>><?php echo esc_html($gb_pv['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="gb_member_expires" class="gb-input gb-input-sm" value="<?php echo esc_attr($gb_m['expires']); ?>" title="Leave blank to set automatically from the plan">
                            <button type="submit" class="gb-btn-small-os">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p style="margin:16px 0 0; color:#94a3b8; font-size:11.5px;">
    Leave the date blank and it is worked out from the plan &mdash; three months for quarterly, twelve for annual.
    Every change is recorded against the customer with who made it and when, so a refund or a dispute can be traced.
</p>
