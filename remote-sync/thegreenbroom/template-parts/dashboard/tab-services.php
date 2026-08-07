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
    .gb-services-toolbar { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:24px; }
    .gb-services-summary { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .gb-services-summary span { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid #e2e8f0; border-radius:999px; background:#fff; color:#64748b; font-size:11px; font-weight:700; }
    .gb-services-summary strong { color:#0f172a; }
    .gb-service-table-wrap { overflow-x:auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 3px rgba(15,23,42,.04); }
    .gb-services-table { width:100%; min-width:980px; border-collapse:collapse; table-layout:fixed; }
    .gb-services-table th { padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:10px; font-weight:800; letter-spacing:.05em; text-align:left; text-transform:uppercase; white-space:nowrap; }
    .gb-services-table td { padding:15px 16px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:13px; vertical-align:middle; }
    .gb-services-table tbody tr:last-child td { border-bottom:0; }
    .gb-services-table tbody tr:hover { background:#fbfefb; }
    .gb-services-table th:nth-child(1) { width:31%; }
    .gb-services-table th:nth-child(2) { width:9%; }
    .gb-services-table th:nth-child(3) { width:10%; }
    .gb-services-table th:nth-child(4) { width:12%; }
    .gb-services-table th:nth-child(5) { width:17%; }
    .gb-services-table th:nth-child(6) { width:15%; }
    .gb-services-table th:nth-child(7) { width:6%; text-align:right; }
    .gb-service-main { display:flex; align-items:center; gap:12px; min-width:0; }
    .gb-service-icon { display:flex; align-items:center; justify-content:center; flex:0 0 36px; width:36px; height:36px; border-radius:9px; background:#f0fdf4; font-size:19px; }
    .gb-service-heading { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .gb-service-heading h2 { margin:0; color:#1b5e20; font-size:15px; line-height:1.3; }
    .gb-service-description { margin:4px 0 0; color:#64748b; font-size:12px; line-height:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .gb-service-detail-label { display:block; color:#94a3b8; font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
    .gb-service-detail-value { display:block; margin-top:4px; color:#0f172a; font-size:13px; font-weight:700; }
    .gb-service-integration { min-width:0; }
    .gb-service-integration strong { display:block; color:#334155; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .gb-service-integration small { display:block; margin-top:4px; color:#64748b; font-size:11px; line-height:1.35; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .gb-service-integration .gb-pill-ok, .gb-service-integration .gb-pill-muted, .gb-service-integration .gb-pill-warn { display:inline-block; margin-top:4px; }
    .gb-service-actions { position:relative; text-align:right; }
    .gb-service-actions summary { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border:1px solid #e2e8f0; border-radius:8px; color:#475569; cursor:pointer; list-style:none; }
    .gb-service-actions summary::-webkit-details-marker { display:none; }
    .gb-service-actions summary:hover, .gb-service-actions[open] summary { background:#f8fafc; border-color:#cbd5e1; color:#1b5e20; }
    .gb-service-actions-menu { position:absolute; z-index:20; top:40px; right:0; min-width:150px; padding:6px; background:#fff; border:1px solid #e2e8f0; border-radius:9px; box-shadow:0 10px 25px rgba(15,23,42,.14); text-align:left; }
    .gb-service-actions-menu a { display:block; padding:9px 10px; border-radius:6px; color:#334155; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap; }
    .gb-service-actions-menu a:hover { background:#f8fafc; color:#1b5e20; }
    .gb-service-actions-menu a.danger { color:#b91c1c; }
    .gb-service-actions-menu a.danger:hover { background:#fef2f2; }
    .gb-service-empty { padding:55px 20px; text-align:center; background:#fff; border:1px solid #e2e8f0; border-radius:12px; color:#64748b; }
    @media (max-width: 760px) {
        .gb-services-toolbar { align-items:flex-start; flex-direction:column; }
        .gb-services-toolbar > a { width:100%; text-align:center; }
        .gb-service-table-wrap { border-radius:10px; }
    }
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
    <?php
    $services = new WP_Query([
        'post_type' => 'gb_service',
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft'],
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $service_total = (int) $services->found_posts;
    $service_live_total = 0;
    foreach ($services->posts as $service_post) {
        if ($service_post->post_status === 'publish') {
            $service_live_total++;
        }
    }
    ?>
    <div class="gb-services-toolbar">
        <div>
            <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Service Configuration</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Your services are your product catalog. Add, hide, and sync products here.</p>
            <div class="gb-services-summary" aria-label="Service catalog summary">
                <span><strong><?php echo esc_html($service_live_total); ?></strong> live</span>
                <span><strong><?php echo esc_html($service_total - $service_live_total); ?></strong> hidden</span>
                <span><strong><?php echo esc_html($service_total); ?></strong> total</span>
            </div>
        </div>
        <a href="?tab=services&action=edit&id=0" class="gb-btn-primary-os">+ Add Service</a>
    </div>

    <div class="gb-service-table-wrap">
        <table class="gb-services-table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Surcharge</th>
                    <th>Stripe product</th>
                    <th>Destination</th>
                    <th><span class="screen-reader-text">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($services->have_posts()):
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
                        $destination_state = 'No cleaner account selected';
                        if ($destination_cleaner_id) {
                            $destination_post = get_post($destination_cleaner_id);
                            if ($destination_post) {
                                $destination_label = $destination_post->post_title;
                                $destination_state = $destination_account ? 'Connected for storefront payments' : 'Cleaner account not connected';
                            }
                        }
                        $stripe_error_label = $stripe_last_error;
                        if (stripos($stripe_last_error, 'api key') !== false || stripos($stripe_last_error, 'invalid api') !== false) {
                            $stripe_error_label = 'Stripe rejected the saved API key. Check the active mode credentials in Settings.';
                        } elseif ($stripe_error_label !== '') {
                            $stripe_error_label = wp_trim_words($stripe_error_label, 12, '...');
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
                        $delete_url = wp_nonce_url(
                            add_query_arg([
                                'action' => 'delete_entity',
                                'id' => $service_id,
                            ], home_url('/manager-dashboard')),
                            'gb_manager_action_delete_entity_' . $service_id
                        );
                ?>
                    <tr>
                        <td>
                            <div class="gb-service-main">
                            <div class="gb-service-icon" aria-hidden="true"><?php echo esc_html($icon ?: '🧹'); ?></div>
                            <div>
                                <div class="gb-service-heading">
                                    <h2><?php the_title(); ?></h2>
                                </div>
                                <p class="gb-service-description"><?php echo esc_html(wp_trim_words(get_the_content(), 16)); ?></p>
                            </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($status === 'publish'): ?><span class="gb-pill-ok">Live</span><?php else: ?><span class="gb-pill-muted">Hidden</span><?php endif; ?>
                        </td>
                        <td><span class="gb-service-detail-value"><?php echo esc_html(max(1, $dur)); ?> hours</span></td>
                        <td><span class="gb-service-detail-value"><?php echo esc_html('£' . number_format($price, 2)); ?>/hr</span></td>
                        <td>
                            <div class="gb-service-integration">
                            <?php if ($stripe_product_id !== ''): ?>
                                <span class="gb-pill-ok">Synced</span>
                                <small title="<?php echo esc_attr($stripe_product_id); ?>"><?php echo esc_html($stripe_product_id); ?></small>
                            <?php elseif ($stripe_last_error !== ''): ?>
                                <span class="gb-pill-warn">Error</span>
                                <small title="<?php echo esc_attr($stripe_error_label); ?>"><?php echo esc_html($stripe_error_label); ?></small>
                            <?php else: ?>
                                <span class="gb-pill-muted">Pending</span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="gb-service-integration"><span class="gb-service-detail-label">Destination</span><strong><?php echo esc_html($destination_label); ?></strong><small style="color:#64748b;"><?php echo esc_html($destination_state); ?></small></div>
                        </td>
                        <td>
                            <details class="gb-service-actions">
                                <summary aria-label="Actions for <?php echo esc_attr(get_the_title()); ?>" title="Service actions"><span class="dashicons dashicons-ellipsis"></span></summary>
                                <div class="gb-service-actions-menu">
                                    <a href="?tab=services&action=edit&id=<?php echo $service_id; ?>">Edit service</a>
                                    <a href="<?php echo esc_url($sync_url); ?>">Sync to Stripe</a>
                                    <a href="<?php echo esc_url($toggle_url); ?>"><?php echo esc_html($toggle_label); ?> service</a>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="danger" onclick="return confirm('Delete this service? It will be moved to trash.');">Delete service</a>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7"><div class="gb-service-empty"><h3 style="margin:0 0 6px; color:#475569;">No services defined</h3><p style="margin:0;">Add your first service to start building the booking catalog.</p></div></td></tr>
                <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
