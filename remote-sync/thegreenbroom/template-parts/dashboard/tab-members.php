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

$gb_counts = ['active' => 0, 'expired' => 0, 'none' => 0, 'cancelled' => 0];
$gb_rows = [];
foreach ($gb_customers as $gb_u) {
    $gb_m = gb_get_membership($gb_u->ID);
    $gb_counts[$gb_m['status']] = ($gb_counts[$gb_m['status']] ?? 0) + 1;
    $gb_rows[] = [$gb_u, $gb_m];
}
?>

<div class="booking-controls">
    <div>
        <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Customer Memberships</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Who has paid for access to the cleaner database.</p>
    </div>
</div>

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
<div class="gb-grid-stats" style="margin-bottom:24px;">
    <?php foreach ([
        'active'    => ['Active members', '#16a34a'],
        'expired'   => ['Expired', '#d97706'],
        'cancelled' => ['Cancelled', '#64748b'],
        'none'      => ['No membership', '#94a3b8'],
    ] as $gb_k => $gb_cfg): ?>
        <div class="gb-widget gb-widget-stat">
            <div class="gb-widget-body">
                <div class="gb-stat-val" style="color:<?php echo esc_attr($gb_cfg[1]); ?>;"><?php echo (int) ($gb_counts[$gb_k] ?? 0); ?></div>
                <div class="gb-stat-label"><?php echo esc_html($gb_cfg[0]); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="gb-card" style="padding:0; overflow:hidden; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
    <div class="gb-table-scroll">
        <table class="gb-booking-table gb-members-table">
            <thead><tr><th style="width:28%;">Customer</th><th>Status</th><th>Plan</th><th>Renews / ended</th><th style="text-align:right;">Set membership</th></tr></thead>
            <tbody>
            <?php if (empty($gb_rows)): ?>
                <tr><td colspan="5" style="color:#64748b; font-size:13px;">No customer accounts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($gb_rows as [$gb_u, $gb_m]):
                $gb_colour = ['active' => '#16a34a', 'expired' => '#d97706', 'cancelled' => '#64748b'][$gb_m['status']] ?? '#94a3b8'; ?>
                <tr>
                    <td>
                        <strong style="font-size:14px; color:#0f172a;"><?php echo esc_html($gb_u->display_name); ?></strong>
                        <div style="font-size:11px; color:#94a3b8; margin-top:3px;"><?php echo esc_html($gb_u->user_email); ?></div>
                    </td>
                    <td>
                        <span style="display:inline-block; white-space:nowrap; padding:5px 11px; border-radius:20px; font-size:10px; font-weight:800; text-transform:uppercase; background:<?php echo esc_attr($gb_colour); ?>1a; color:<?php echo esc_attr($gb_colour); ?>;">
                            <?php echo esc_html($gb_m['status'] === 'none' ? 'Not a member' : $gb_m['status']); ?>
                        </span>
                        <?php if ($gb_m['source'] === 'manual' && $gb_m['status'] !== 'none'): ?>
                            <div style="font-size:10.5px; color:#94a3b8; margin-top:4px;">Recorded by hand</div>
                        <?php endif; ?>
                        <?php if (!empty($gb_m['cancels_at'])): ?>
                            <div style="font-size:10.5px; color:#d97706; margin-top:4px;">Cancelled &mdash; not renewing</div>
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
                    <td style="font-size:12.5px; color:#475569; white-space:nowrap;">
                        <?php echo $gb_m['expires'] ? esc_html(date_i18n('j M Y', strtotime($gb_m['expires']))) : '&mdash;'; ?>
                        <?php if (!empty($gb_m['cancels_at'])): ?>
                            <div style="font-size:10.5px; color:#d97706;">ends, no renewal</div>
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
                        <?php
                        /*
                         * Ask Stripe again.
                         *
                         * The webhook normally keeps this current and when it
                         * is working this is never needed. It is for when it
                         * is not: a delivery that failed, an event type never
                         * subscribed to, or a change made in Stripe before the
                         * site was listening. Without it the only correction
                         * available is to type the right answer in by hand,
                         * which records a manual override rather than the
                         * truth, and tells nobody which of those it was.
                         */
                        $gb_sub_id = (string) get_user_meta($gb_u->ID, '_gb_stripe_subscription_id', true);
                        if ($gb_sub_id !== ''):
                            $gb_sub_status = (string) get_user_meta($gb_u->ID, '_gb_stripe_subscription_status', true);
                        ?>
                            <form method="post" style="margin-top:6px; display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                <input type="hidden" name="gb_save_action" value="sync_membership">
                                <input type="hidden" name="gb_member_user" value="<?php echo (int) $gb_u->ID; ?>">
                                <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
                                <span style="font-size:10.5px; color:#94a3b8;" title="<?php echo esc_attr($gb_sub_id); ?>">
                                    Stripe says <strong><?php echo esc_html($gb_sub_status !== '' ? $gb_sub_status : 'unknown'); ?></strong>
                                </span>
                                <button type="submit" class="gb-btn-small-os">Sync from Stripe</button>
                            </form>
                        <?php endif; ?>
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
