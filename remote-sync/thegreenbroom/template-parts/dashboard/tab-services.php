<?php
$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
$stripe_sync_state = isset($_GET['stripe_sync']) ? sanitize_key(wp_unslash($_GET['stripe_sync'])) : '';
$stripe_sync_msg = isset($_GET['stripe_sync_msg']) ? sanitize_text_field(wp_unslash($_GET['stripe_sync_msg'])) : '';

$connected_cleaners = get_posts([
    'post_type' => 'gb_cleaner',
    'post_status' => ['publish', 'pending', 'draft'],
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
]);
?>

<style>
    .gb-services-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .gb-config-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; }
    .gb-btn-small-os {
        text-decoration: none; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 6px;
        color: #334155; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center;
        transition: all 0.2s ease; background: white; cursor: pointer; text-transform: uppercase;
    }
    .gb-btn-small-os:hover { background: #f8fafc; border-color: #cbd5e1; }
    .gb-btn-primary-os { background: #2e7d32; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 13px; }
    .gb-pill-ok { background:#dcfce7; color:#166534; border-radius:20px; padding:4px 10px; font-size:10px; font-weight:700; text-transform:uppercase; }
    .gb-pill-muted { background:#f1f5f9; color:#475569; border-radius:20px; padding:4px 10px; font-size:10px; font-weight:700; text-transform:uppercase; }
    .gb-pill-warn { background:#fffbeb; color:#92400e; border-radius:20px; padding:4px 10px; font-size:10px; font-weight:700; text-transform:uppercase; }
</style>

<?php if ($stripe_sync_state === 'error' && $stripe_sync_msg !== ''): ?>
    <div class="gb-card" style="margin-bottom:16px; background:#fef2f2; border-color:#fecaca; color:#991b1b; padding:12px 14px;">
        Stripe product sync failed: <?php echo esc_html($stripe_sync_msg); ?>
    </div>
<?php elseif ($stripe_sync_state === 'ok'): ?>
    <div class="gb-card" style="margin-bottom:16px; background:#f0fdf4; border-color:#dcfce7; color:#166534; padding:12px 14px;">
        Stripe product synced successfully.
    </div>
<?php endif; ?>

<?php if ($action === 'edit'):
    $pid = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $post = $pid ? get_post($pid) : null;

    $title = $post ? $post->post_title : '';
    $desc = $post ? $post->post_content : '';
    $duration = $post ? get_post_meta($pid, '_gb_service_duration', true) : 2;
    $icon = $post ? get_post_meta($pid, '_gb_service_icon', true) : '🧹';
    $price = $post ? get_post_meta($pid, 'service_price', true) : '0';
    $status = $post ? get_post_status($pid) : 'publish';
    if (!in_array($status, ['publish', 'draft'], true)) {
        $status = 'publish';
    }
    $destination_cleaner_id = $post ? absint(get_post_meta($pid, '_gb_service_destination_cleaner_id', true)) : 0;

    $stripe_product_id = $post ? sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_product_id', true)) : '';
    $stripe_price_id = $post ? sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_price_id', true)) : '';
    $stripe_last_sync = $post ? sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_product_last_sync', true)) : '';
    $stripe_last_error = $post ? sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_product_last_error', true)) : '';
?>
    <form method="post">
        <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="gb_save_action" value="save_service">
        <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

        <div class="gb-edit-header">
            <div>
                <a href="?tab=services" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Services</a>
                <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;"><?php echo $pid ? 'Edit Service Product' : 'Create Service Product'; ?></h1>
                <p style="margin:5px 0 0; color:#64748b; font-size:14px;">This page controls the service and the linked Stripe product.</p>
            </div>
            <button type="submit" class="gb-header-btn" style="height:44px; background:#2e7d32; color:white; border:none; padding:0 30px; border-radius:8px; font-weight:700; cursor:pointer;">Save Service</button>
        </div>

        <div class="gb-config-card">
            <div style="display:grid; grid-template-columns:1fr 260px; gap:20px;">
                <div class="gb-form-row">
                    <label class="gb-label">Service Name</label>
                    <input type="text" name="post_title" class="gb-input" value="<?php echo esc_attr($title); ?>" placeholder="e.g. Deep Clean" required>
                </div>
                <div class="gb-form-row">
                    <label class="gb-label">Status</label>
                    <select name="post_status" class="gb-input" style="height:44px;">
                        <option value="publish" <?php selected($status, 'publish'); ?>>Live (Bookable)</option>
                        <option value="draft" <?php selected($status, 'draft'); ?>>Hidden (Not Bookable)</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin:8px 0 25px;">
                <div>
                    <label class="gb-label">Default Duration (Hours)</label>
                    <input type="number" name="_gb_service_duration" class="gb-input" value="<?php echo esc_attr($duration); ?>" min="1" required>
                    <p style="color:#94a3b8; font-size:12px; margin-top:8px;">Used in booking slot calculations.</p>
                </div>
                <div>
                    <label class="gb-label">Service Icon (Emoji)</label>
                    <input type="text" name="_gb_service_icon" class="gb-input" value="<?php echo esc_attr($icon); ?>" placeholder="Paste an emoji here">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:25px;">
                <div>
                    <label class="gb-label">Hourly Surcharge (£/hr)</label>
                    <input type="number" name="service_price" class="gb-input" value="<?php echo esc_attr($price); ?>" min="0" step="0.01">
                    <p style="color:#94a3b8; font-size:12px; margin-top:8px;">Booking uses cleaner rate + this surcharge. Stripe product sync uses this as product price (min £0.50).</p>
                </div>
                <div>
                    <label class="gb-label">Storefront Destination Cleaner (Optional)</label>
                    <select name="_gb_service_destination_cleaner_id" class="gb-input" style="height:44px;">
                        <option value="0">No destination account mapping</option>
                        <?php foreach ($connected_cleaners as $cleaner):
                            $account_id = sanitize_text_field((string) get_post_meta($cleaner->ID, '_gb_stripe_account_id', true));
                            if ($account_id === '') {
                                continue;
                            }
                        ?>
                            <option value="<?php echo (int) $cleaner->ID; ?>" <?php selected($destination_cleaner_id, (int) $cleaner->ID); ?>>
                                <?php echo esc_html($cleaner->post_title . ' - ' . $account_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="color:#94a3b8; font-size:12px; margin-top:8px;">Used for destination-charge storefront purchases only.</p>
                </div>
            </div>

            <div class="gb-form-row">
                <label class="gb-label">Description (Displayed on Booking Page)</label>
                <textarea name="post_content" class="gb-textarea" rows="6" style="height:auto;"><?php echo esc_textarea($desc); ?></textarea>
            </div>

            <div style="margin-top:22px; border-top:1px solid #f1f5f9; padding-top:16px;">
                <h4 style="margin:0 0 10px; font-size:13px; color:#0f172a;">Stripe Product Link</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:12px; color:#334155;">
                    <div>Product ID: <strong><?php echo esc_html($stripe_product_id ?: 'Not synced yet'); ?></strong></div>
                    <div>Default Price ID: <strong><?php echo esc_html($stripe_price_id ?: 'Not synced yet'); ?></strong></div>
                    <div>Last Sync: <strong><?php echo esc_html($stripe_last_sync ?: 'Never'); ?></strong></div>
                    <div>Last Error: <strong><?php echo esc_html($stripe_last_error ?: 'None'); ?></strong></div>
                </div>
                <?php if ($pid): ?>
                    <?php
                    $sync_url = wp_nonce_url(
                        add_query_arg([
                            'tab' => 'services',
                            'action' => 'sync_service_product',
                            'id' => $pid,
                        ], home_url('/manager-dashboard')),
                        'gb_manager_action_sync_service_product_' . $pid
                    );
                    ?>
                    <div style="margin-top:10px;">
                        <a href="<?php echo esc_url($sync_url); ?>" class="gb-btn-small-os">Sync To Stripe Now</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

<?php else: ?>
    <div class="booking-controls">
        <div>
            <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Service Configuration</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Your services are your product catalog. Add, hide, and sync products here.</p>
        </div>
        <a href="?tab=services&action=edit&id=0" class="gb-btn-primary-os">+ Add Service</a>
    </div>

    <div class="gb-card" style="padding:0; overflow:hidden;">
        <table class="gb-booking-table">
            <thead>
                <tr>
                    <th style="width:60px; text-align:center;">Icon</th>
                    <th>Service</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:120px;">Duration</th>
                    <th style="width:120px;">Surcharge</th>
                    <th style="width:190px;">Stripe Product</th>
                    <th style="width:170px;">Destination</th>
                    <th style="text-align:right; width:240px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $services = new WP_Query([
                    'post_type' => 'gb_service',
                    'posts_per_page' => -1,
                    'post_status' => ['publish', 'draft'],
                    'orderby' => 'menu_order title',
                    'order' => 'ASC',
                ]);
                if ($services->have_posts()):
                    while ($services->have_posts()): $services->the_post();
                        $service_id = get_the_ID();
                        $dur = (int) get_post_meta($service_id, '_gb_service_duration', true);
                        $icon = (string) get_post_meta($service_id, '_gb_service_icon', true);
                        $price = (float) get_post_meta($service_id, 'service_price', true);
                        $status = get_post_status($service_id);
                        $stripe_product_id = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_product_id', true));
                        $stripe_last_error = sanitize_text_field((string) get_post_meta($service_id, '_gb_stripe_product_last_error', true));
                        $destination_cleaner_id = absint(get_post_meta($service_id, '_gb_service_destination_cleaner_id', true));
                        $destination_account = function_exists('gb_get_service_destination_account_id') ? gb_get_service_destination_account_id($service_id) : '';
                        $destination_label = 'Not mapped';
                        if ($destination_cleaner_id) {
                            $destination_post = get_post($destination_cleaner_id);
                            if ($destination_post) {
                                $destination_label = $destination_post->post_title . ($destination_account ? ' - ' . $destination_account : '');
                            }
                        }

                        $toggle_target = $status === 'publish' ? 'draft' : 'publish';
                        $toggle_label = $status === 'publish' ? 'Hide' : 'Restore';
                        $toggle_url = wp_nonce_url(
                            add_query_arg([
                                'tab' => 'services',
                                'action' => 'toggle_service',
                                'id' => $service_id,
                                'state' => $toggle_target,
                            ], home_url('/manager-dashboard')),
                            'gb_manager_action_toggle_service_' . $service_id
                        );
                        $sync_url = wp_nonce_url(
                            add_query_arg([
                                'tab' => 'services',
                                'action' => 'sync_service_product',
                                'id' => $service_id,
                            ], home_url('/manager-dashboard')),
                            'gb_manager_action_sync_service_product_' . $service_id
                        );
                ?>
                    <tr>
                        <td style="font-size:24px; text-align:center;"><?php echo esc_html($icon ?: '🧹'); ?></td>
                        <td>
                            <strong style="color:#1b5e20; font-size:15px;"><?php the_title(); ?></strong>
                            <div style="color:#64748b; font-size:12px; margin-top:4px;"><?php echo esc_html(wp_trim_words(get_the_content(), 10)); ?></div>
                        </td>
                        <td>
                            <?php if ($status === 'publish'): ?>
                                <span class="gb-pill-ok">Live</span>
                            <?php else: ?>
                                <span class="gb-pill-muted">Hidden</span>
                            <?php endif; ?>
                        </td>
                        <td><span style="font-weight:700; color:#0f172a; font-size:13px;"><?php echo esc_html(max(1, $dur)); ?> Hours</span></td>
                        <td><span style="font-weight:700; color:#0f172a; font-size:13px;"><?php echo esc_html('£' . number_format($price, 2)); ?>/hr</span></td>
                        <td>
                            <?php if ($stripe_product_id !== ''): ?>
                                <span class="gb-pill-ok">Synced</span>
                                <div style="font-size:11px; color:#64748b; margin-top:4px;"><?php echo esc_html($stripe_product_id); ?></div>
                            <?php elseif ($stripe_last_error !== ''): ?>
                                <span class="gb-pill-warn">Error</span>
                                <div style="font-size:11px; color:#b45309; margin-top:4px;"><?php echo esc_html($stripe_last_error); ?></div>
                            <?php else: ?>
                                <span class="gb-pill-muted">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px; color:#334155;"><?php echo esc_html($destination_label); ?></td>
                        <td style="text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
                                <a href="?tab=services&action=edit&id=<?php echo $service_id; ?>" class="gb-btn-small-os">Edit</a>
                                <a href="<?php echo esc_url($sync_url); ?>" class="gb-btn-small-os">Sync</a>
                                <a href="<?php echo esc_url($toggle_url); ?>" class="gb-btn-small-os"><?php echo esc_html($toggle_label); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:80px 0;"><h3 style="color:#94a3b8;">No services defined</h3></td></tr>
                <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
