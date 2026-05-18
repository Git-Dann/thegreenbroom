<?php 
$action = isset($_GET['action']) ? $_GET['action'] : ''; 
$status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'any';
$allowed_status_filters = ['any', 'confirmed', 'pending', 'cancelled'];
if (!in_array($status_filter, $allowed_status_filters, true)) {
    $status_filter = 'any';
}

$gb_resolve_booking_flow = static function($booking_id, $post_status = '') {
    $flow_status = sanitize_key((string) get_post_meta($booking_id, '_gb_booking_status', true));
    if ($flow_status !== '') {
        return $flow_status;
    }

    if ($post_status === 'publish') {
        return 'confirmed';
    }
    if ($post_status === 'trash') {
        return 'cancelled';
    }
    return 'pending_payment';
};

$gb_flow_label = static function($flow_status) {
    $labels = [
        'confirmed' => 'Confirmed',
        'pending_payment' => 'Pending Payment',
        'cancelled' => 'Cancelled',
    ];
    return isset($labels[$flow_status]) ? $labels[$flow_status] : ucfirst(str_replace('_', ' ', $flow_status));
};

$gb_flow_class = static function($flow_status) {
    $classes = [
        'confirmed' => 'status-confirmed',
        'pending_payment' => 'status-pending',
        'cancelled' => 'status-cancelled',
    ];
    return isset($classes[$flow_status]) ? $classes[$flow_status] : 'status-pending';
};

$gb_payment_label = static function($payment_status, $provider = '') {
    if ($provider !== 'stripe' && $payment_status === '') {
        return 'N/A';
    }
    if ($payment_status === 'paid') {
        return 'Paid';
    }
    if ($payment_status === 'unpaid') {
        return 'Unpaid';
    }
    if ($payment_status === 'failed') {
        return 'Failed';
    }
    return 'Pending';
};

$gb_payment_class = static function($payment_status) {
    if ($payment_status === 'paid') {
        return 'status-paid';
    }
    if ($payment_status === 'failed') {
        return 'status-failed';
    }
    return 'status-awaiting';
};
?>

<style>
    /* --- SHARED & LIST VIEW STYLES --- */
    .gb-booking-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .gb-booking-table th { text-align: left; padding: 12px 15px; border-bottom: 2px solid #f1f5f9; color: #64748b; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
    .gb-booking-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    
    .status-confirmed { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; }
    .status-pending { background: #fef3c7; color: #9a3412; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; }
    .status-cancelled { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; }
    .status-paid { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; }
    .status-awaiting { background: #fff7ed; color: #9a3412; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; }
    .status-failed { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; }

    .cleaner-tag { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; color: #475569; font-weight: 600; text-decoration: none; }
    .cleaner-tag:hover { background: #e2e8f0; }

    .booking-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .btn-os { background: #ffffff; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #334155; text-decoration: none; }
    .btn-os.active { background: #2e7d32; color: #fff; border-color: #2e7d32; }

    /* --- MANAGE APPOINTMENT (EDIT) STYLES --- */
    .gb-edit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    
    /* Header Dropdown & Button Scaling */
    .gb-header-actions { display: flex; gap: 12px; align-items: center; }
    .gb-header-select { height: 44px; padding: 0 15px; border-radius: 8px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 14px; background: #fff; min-width: 180px; }
    .gb-header-btn { height: 44px; background: #2e7d32; color: white; border: none; padding: 0 25px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
    .gb-header-btn:hover { background: #1b5e20; }

    /* Section Headings with Larger Icons */
    .gb-card-head { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-weight: 700; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; display: flex; align-items: center; }
    .gb-card-head .dashicons { font-size: 20px; width: 20px; height: 20px; margin-right: 12px; color: #1b5e20; }

    /* Summary Dashboard */
    .gb-summary-bar { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 25px; display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); text-align: center; }
    .gb-summary-item { border-right: 1px solid #f1f5f9; }
    .gb-summary-item:last-child { border-right: none; }
    .gb-summary-val { display: block; font-size: 20px; font-weight: 800; color: #1b5e20; margin-bottom: 4px; }
    .gb-summary-lab { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; }

    /* Client Sidebar & Quick Action */
    .gb-mgmt-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; }
    .gb-quick-action { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 20px; margin-top: 20px; }
    .gb-qa-title { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; color: #92400e; margin-bottom: 12px; }
    .gb-qa-title .dashicons { font-size: 18px; width: 18px; height: 18px; }
    .gb-qa-btn { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #fde68a; padding: 12px 16px; border-radius: 8px; color: #92400e; font-weight: 700; text-decoration: none; font-size: 13px; transition: 0.2s; }
    .gb-qa-btn:hover { background: #fff9db; border-color: #f59e0b; }
    .gb-qa-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }

    /* System Log Box */
    .gb-log-box { background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-top: 20px; }
    .gb-log-item { font-size: 13px; color: #64748b; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    .gb-log-item strong { color: #334155; }
</style>

<?php if ($action == 'edit' && isset($_GET['id'])): 
    // --- MANAGE APPOINTMENT VIEW ---
    $pid = intval($_GET['id']);
    $post = get_post($pid);
    if (!$post || $post->post_type !== 'gb_booking') {
        echo '<div class="gb-card">Booking not found.</div>';
        return;
    }
    
    $cleaner_id = get_post_meta($pid, '_gb_booking_cleaner_id', true);
    $date       = get_post_meta($pid, '_gb_booking_date', true);
    $time       = get_post_meta($pid, '_gb_booking_time', true);
    $duration   = get_post_meta($pid, '_gb_booking_duration', true) ?: 2;
    $service    = get_post_meta($pid, '_gb_booking_service', true);
    $cust_name  = get_post_meta($pid, '_gb_customer_name', true);
    $cust_email = get_post_meta($pid, '_gb_customer_email', true);
    $cust_addr  = get_post_meta($pid, '_gb_customer_address', true);
    $booking_total = (float) get_post_meta($pid, '_gb_booking_total_gbp', true);
    $platform_fee_gbp = (float) get_post_meta($pid, '_gb_platform_fee_gbp', true);
    $cleaner_net_gbp = (float) get_post_meta($pid, '_gb_cleaner_payout_estimate_gbp', true);
    $destination_account_id = sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_destination_account', true));
    $payment_provider = sanitize_key((string) get_post_meta($pid, '_gb_payment_provider', true));
    $payment_status = sanitize_key((string) get_post_meta($pid, '_gb_payment_status', true));
    $flow_status = $gb_resolve_booking_flow($pid, $post ? $post->post_status : '');
    $flow_label = $gb_flow_label($flow_status);
    $status_color_map = [
        'confirmed' => '#10b981',
        'pending_payment' => '#f59e0b',
        'cancelled' => '#ef4444',
    ];
    $status_color = isset($status_color_map[$flow_status]) ? $status_color_map[$flow_status] : '#64748b';
?>
    <form method="post">
        <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="gb_save_action" value="save_booking">
        <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
        
        <div class="gb-edit-header">
            <div>
                <a href="?tab=bookings" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Bookings</a>
                <h1 class="gb-title" style="margin-top:10px;">Appointment #<?php echo $pid; ?></h1>
                <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Manage schedule, assignment, and customer details.</p>
                <div style="display:flex; align-items:center; gap:8px; margin-top:5px;">
                    <span style="width:8px; height:8px; border-radius:50%; background:<?php echo $status_color; ?>;"></span>
                    <span style="font-size:12px; font-weight:700; color:<?php echo $status_color; ?>; text-transform:uppercase;">
                        <?php echo esc_html($flow_label); ?>
                    </span>
                </div>
            </div>
            <div class="gb-header-actions">
                <select name="post_status" class="gb-header-select">
                    <option value="publish" <?php selected($post->post_status, 'publish'); ?>>Confirm Booking</option>
                    <option value="pending" <?php selected($post->post_status, 'pending'); ?>>Set to Pending</option>
                    <option value="trash" <?php selected($post->post_status, 'trash'); ?>>Cancel Appointment</option>
                </select>
                <button type="submit" class="gb-header-btn">
                    Update Appointment
                </button>
            </div>
        </div>

        <div class="gb-summary-bar">
            <div class="gb-summary-item">
                <span class="gb-summary-lab">Service</span>
                <span class="gb-summary-val"><?php echo esc_html($service); ?></span>
            </div>
            <div class="gb-summary-item">
                <span class="gb-summary-lab">Length</span>
                <span class="gb-summary-val"><?php echo $duration; ?> Hours</span>
            </div>
            <div class="gb-summary-item">
                <span class="gb-summary-lab">Customer</span>
                <span class="gb-summary-val"><?php echo esc_html($cust_name); ?></span>
            </div>
            <div class="gb-summary-item">
                <span class="gb-summary-lab">Flow</span>
                <span class="gb-summary-val" style="font-size:16px;"><?php echo esc_html($flow_label); ?></span>
            </div>
            <div class="gb-summary-item">
                <span class="gb-summary-lab">Payment</span>
                <span class="gb-summary-val" style="font-size:16px;"><?php echo esc_html($gb_payment_label($payment_status, $payment_provider)); ?></span>
                <?php if ($booking_total > 0): ?>
                    <div style="margin-top:4px; font-size:11px; color:#94a3b8;">£<?php echo number_format($booking_total, 2); ?></div>
                <?php endif; ?>
                <?php if ($platform_fee_gbp > 0 || $cleaner_net_gbp > 0): ?>
                    <div style="margin-top:2px; font-size:10px; color:#94a3b8;">Fee £<?php echo number_format($platform_fee_gbp, 2); ?> / Net £<?php echo number_format($cleaner_net_gbp, 2); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="gb-grid" style="grid-template-columns: 2fr 1fr; gap:25px; align-items:start;">
            <div class="gb-card">
                <div class="gb-card-head">
                    <span class="dashicons dashicons-calendar-alt"></span> Scheduling & Assignment
                </div>
                <div class="gb-card-body" style="padding:25px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;">
                        <div>
                            <label class="gb-mgmt-label">Visit Date</label>
                            <input type="date" name="_gb_booking_date" class="gb-input" value="<?php echo esc_attr($date); ?>">
                        </div>
                        <div>
                            <label class="gb-mgmt-label">Arrival Window</label>
                            <input type="time" name="_gb_booking_time" class="gb-input" value="<?php echo esc_attr($time); ?>">
                        </div>
                    </div>
                    
                    <div style="margin-bottom:25px;">
                        <label class="gb-mgmt-label">Assigned Professional</label>
                        <select name="_gb_booking_cleaner_id" class="gb-input">
                            <option value="">-- Click to Assign --</option>
                            <?php 
                            $cleaners = get_posts(['post_type'=>'gb_cleaner', 'post_status'=>'publish', 'numberposts'=>-1]);
                            foreach($cleaners as $c) {
                                echo '<option value="'.$c->ID.'" '.selected($cleaner_id, $c->ID, false).'>'.$c->post_title.' (Verified)</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div>
                            <label class="gb-mgmt-label">Service Type</label>
                            <input type="text" name="_gb_booking_service" class="gb-input" value="<?php echo esc_attr($service); ?>">
                        </div>
                        <div>
                            <label class="gb-mgmt-label">Length (Hours)</label>
                            <input type="number" name="_gb_booking_duration" class="gb-input" value="<?php echo esc_attr($duration); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="gb-sidebar-panels">
                <div class="gb-card">
                    <div class="gb-card-head">
                        <span class="dashicons dashicons-admin-users"></span> Client Details
                    </div>
                    <div class="gb-card-body" style="padding:25px;">
                        <div style="margin-bottom:20px;">
                            <label class="gb-mgmt-label">Contact Name</label>
                            <input type="text" name="_gb_customer_name" class="gb-input" value="<?php echo esc_attr($cust_name); ?>">
                        </div>
                        <div style="margin-bottom:20px;">
                            <label class="gb-mgmt-label">Email Address</label>
                            <input type="email" name="_gb_customer_email" class="gb-input" value="<?php echo esc_attr($cust_email); ?>">
                        </div>
                        <div>
                            <label class="gb-mgmt-label">Service Address</label>
                            <textarea name="_gb_customer_address" class="gb-input" style="height:100px;"><?php echo esc_textarea($cust_addr); ?></textarea>
                        </div>

                        <div class="gb-quick-action">
                            <div class="gb-qa-title">
                                <span class="dashicons dashicons-megaphone"></span> Quick Action
                            </div>
                            <a href="mailto:<?php echo esc_attr($cust_email); ?>?subject=Update regarding your booking #<?php echo $pid; ?>" class="gb-qa-btn">
                                Email Client <span class="dashicons dashicons-external"></span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="gb-log-box">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px; font-weight:800; color:#64748b; font-size:11px; text-transform:uppercase;">
                        <span class="dashicons dashicons-shield"></span> System Log
                    </div>
                    <div class="gb-log-item">Booking Created: <strong><?php echo get_the_date('M j, Y', $pid); ?></strong></div>
                    <div class="gb-log-item">Flow Status: <strong><?php echo esc_html($flow_label); ?></strong></div>
                    <div class="gb-log-item">Payment Status: <strong><?php echo esc_html($gb_payment_label($payment_status, $payment_provider)); ?></strong></div>
                    <?php if ($destination_account_id !== ''): ?>
                        <div class="gb-log-item">Connected Account: <strong><?php echo esc_html($destination_account_id); ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

<?php else: 
    // --- LIST VIEW (BASE FUNCTIONS PRESERVED) ---
?>
    <div class="booking-controls">
        <div>
            <h1 class="gb-title">Bookings</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Track appointment status, assignments, and visit timing.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="?tab=bookings&status=any" class="btn-os <?php echo $status_filter == 'any' ? 'active' : ''; ?>">All</a>
            <a href="?tab=bookings&status=confirmed" class="btn-os <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
            <a href="?tab=bookings&status=pending" class="btn-os <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?tab=bookings&status=cancelled" class="btn-os <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>
    </div>

    <div class="gb-card" style="padding:0; overflow:hidden;">
        <table class="gb-booking-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Assigned To</th>
                    <th>Flow Status</th>
                    <th>Payment</th>
                    <th>Total</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $args = [
                    'post_type' => 'gb_booking',
                    'posts_per_page' => 50,
                    'post_status' => ['publish', 'pending', 'trash'],
                    'orderby' => 'date',
                    'order' => 'DESC',
                ];
                $query = new WP_Query($args);
                $has_visible_rows = false;

                if($query->have_posts()): while($query->have_posts()): $query->the_post(); 
                    $bid = get_the_ID();
                    $date = get_post_meta($bid, '_gb_booking_date', true);
                    $time = get_post_meta($bid, '_gb_booking_time', true);
                    $cid = get_post_meta($bid, '_gb_booking_cleaner_id', true);
                    $service = get_post_meta($bid, '_gb_booking_service', true);
                    $status = get_post_status($bid);
                    $flow_status = $gb_resolve_booking_flow($bid, $status);
                    $payment_status = sanitize_key((string) get_post_meta($bid, '_gb_payment_status', true));
                    $payment_provider = sanitize_key((string) get_post_meta($bid, '_gb_payment_provider', true));
                    $total_gbp = (float) get_post_meta($bid, '_gb_booking_total_gbp', true);

                    if ($status_filter === 'confirmed' && $flow_status !== 'confirmed') {
                        continue;
                    }
                    if ($status_filter === 'pending' && !in_array($flow_status, ['pending_payment', 'pending'], true)) {
                        continue;
                    }
                    if ($status_filter === 'cancelled' && $flow_status !== 'cancelled') {
                        continue;
                    }
                    $has_visible_rows = true;
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700; color:#1b5e20;"><?php echo date('M j, Y', strtotime($date)); ?></div>
                        <div style="color:#94a3b8; font-size:11px;"><?php echo $time; ?></div>
                    </td>
                    <td><div style="font-weight:600;"><?php echo get_post_meta($bid, '_gb_customer_name', true); ?></div></td>
                    <td><div style="text-transform: capitalize;"><?php echo esc_html($service); ?></div></td>
                    <td>
                        <?php if($cid): ?>
                            <a href="?tab=cleaners&action=edit&id=<?php echo $cid; ?>" class="cleaner-tag">
                                <span class="dashicons dashicons-admin-users"></span> <?php echo get_the_title($cid); ?>
                            </a>
                        <?php else: ?>
                            <span class="cleaner-tag" style="border:1px dashed #cbd5e1; color:#94a3b8; background:transparent;">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?php echo esc_attr($gb_flow_class($flow_status)); ?>">
                            <?php echo esc_html($gb_flow_label($flow_status)); ?>
                        </span>
                    </td>
                    <td><span class="<?php echo esc_attr($gb_payment_class($payment_status)); ?>"><?php echo esc_html($gb_payment_label($payment_status, $payment_provider)); ?></span></td>
                    <td style="font-weight:700; color:#0f172a;"><?php echo $total_gbp > 0 ? '£' . number_format($total_gbp, 2) : '—'; ?></td>
                    <td style="text-align:right;"><a href="?tab=bookings&action=edit&id=<?php echo $bid; ?>" class="gb-btn-small-os">Manage</a></td>
                </tr>
                <?php endwhile; if (!$has_visible_rows): ?>
                    <tr><td colspan="8" style="text-align:center; padding:50px; color:#94a3b8;">No bookings found.</td></tr>
                <?php endif; wp_reset_postdata(); else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:50px; color:#94a3b8;">No bookings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
