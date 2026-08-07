<?php $action = isset($_GET['action']) ? $_GET['action'] : ''; ?>

<style>
    /* --- NEW CORE UI TOKENS (No Bloat) --- */
    .gb-os-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
    
    /* SEAMLESS EDGE HEADERS: Border spans absolute full width */
    .gb-os-head { border-bottom: 1px solid #f1f5f9; background: #fff; }
    .gb-os-head-content { padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; }
    .gb-os-title { font-weight: 800; font-size: 10px; text-transform: uppercase; color: #64748b; letter-spacing: 0.6px; display: flex; align-items: center; gap: 8px; }
    .gb-os-title .dashicons { font-size: 16px; width: 16px; height: 16px; color: #2e7d32; }

    /* UNIFIED BODY PADDING */
    .gb-os-body { padding: 20px; }
    
    /* OS INPUT SYSTEM */
    .gb-os-label { display: block; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .gb-os-input { width: 100%; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #0f172a; font-family: inherit; transition: all 0.2s; background: #fff; }
    .gb-os-input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.05); outline: none; }

    /* IDENTITY HERO */
    .gb-identity-block { display: flex; align-items: center; gap: 24px; padding: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px; }
    .gb-avatar-large { width: 88px; height: 88px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; background: #f8fafc; }

    /* TABLE SYNERGY */
    .gb-table-os { width: 100%; border-collapse: collapse; font-size: 13px; }
    .gb-table-os th { text-align: left; padding: 12px 20px; background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
    .gb-table-os td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    
    /* PILLS & BADGES */
    .pill-active { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-size: 9px; text-transform: uppercase; }
    .pill-trial { background: #fffbeb; color: #92400e; padding: 3px 8px; border-radius: 4px; font-weight: 800; font-size: 9px; text-transform: uppercase; }
    .pill-verified { background: #fff; color: #2e7d32; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 10px; display: inline-flex; align-items: center; gap: 5px; }

    /* GRID UTILITY */
    .gb-os-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
</style>

<?php if ($action == 'edit') {
    $pid = intval($_GET['id']);
    $post = $pid ? get_post($pid) : (object)['ID'=>0, 'post_title'=>'', 'post_content'=>'', 'post_status'=>'draft'];
    
    // Core Data
    $rate = get_post_meta($pid, 'hourly_rate', true);
    $loc = get_post_meta($pid, '_gb_location', true);
    if ($loc === '') {
        $loc = get_post_meta($pid, 'cleaner_location', true);
    }
    if ($loc === '') {
        $loc = get_post_meta($pid, 'location', true);
    }
    $exp = get_post_meta($pid, '_gb_experience', true);
    $avail = get_post_meta($pid, '_gb_availability', true);
    $rating = get_post_meta($pid, '_gb_rating', true);
    $reviews = get_post_meta($pid, '_gb_review_count', true);
    $sub_st = get_post_meta($pid, '_gb_subscription_status', true) ?: 'trial';
    $image_url = get_the_post_thumbnail_url($pid, 'medium') ?: '';
    $stripe_account_id = sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_account_id', true));
    $stripe_charges_enabled = get_post_meta($pid, '_gb_stripe_charges_enabled', true) === '1';
    $stripe_payouts_enabled = get_post_meta($pid, '_gb_stripe_payouts_enabled', true) === '1';
    $stripe_details_submitted = get_post_meta($pid, '_gb_stripe_details_submitted', true) === '1';
    $stripe_requirements_due_count = (int) get_post_meta($pid, '_gb_stripe_requirements_due_count', true);
    $stripe_connect_status = get_post_meta($pid, '_gb_stripe_connect_status', true) ?: 'not_connected';
    $stripe_last_sync = get_post_meta($pid, '_gb_stripe_last_sync', true);
    $stripe_last_error = get_post_meta($pid, '_gb_stripe_last_error', true);
    $payout_interval = gb_normalize_payout_interval(get_post_meta($pid, '_gb_stripe_payout_interval', true));
    $stripe_mode = function_exists('gb_get_stripe_config') ? (gb_get_stripe_config()['mode'] ?? 'test') : 'test';
    $connect_redirect = home_url('/manager-dashboard?tab=cleaners&action=edit&id=' . $pid);
    $connect_onboard_url = wp_nonce_url(
        add_query_arg(
            [
                'action'      => 'gb_connect_onboarding',
                'cleaner_id'  => $pid,
                'redirect_to' => $connect_redirect,
            ],
            admin_url('admin-post.php')
        ),
        'gb_connect_onboarding_' . $pid
    );
    $connect_login_url = wp_nonce_url(
        add_query_arg(
            [
                'action'      => 'gb_connect_login_link',
                'cleaner_id'  => $pid,
                'redirect_to' => $connect_redirect,
            ],
            admin_url('admin-post.php')
        ),
        'gb_connect_login_' . $pid
    );
    $connect_sync_url = wp_nonce_url(
        add_query_arg(
            [
                'action'      => 'gb_connect_sync',
                'cleaner_id'  => $pid,
                'redirect_to' => $connect_redirect,
            ],
            admin_url('admin-post.php')
        ),
        'gb_connect_sync_' . $pid
    );
    $connect_notice = isset($_GET['connect']) ? sanitize_key(wp_unslash($_GET['connect'])) : '';
    $connect_notice_msg = isset($_GET['connect_msg']) ? sanitize_text_field(wp_unslash($_GET['connect_msg'])) : '';
    $connect_status_labels = [
        'active' => 'Active',
        'pending_review' => 'Pending Review',
        'restricted' => 'Restricted',
        'needs_onboarding' => 'Needs Onboarding',
        'not_connected' => 'Not Connected',
    ];
    $connect_status_label = isset($connect_status_labels[$stripe_connect_status]) ? $connect_status_labels[$stripe_connect_status] : ucwords(str_replace('_', ' ', $stripe_connect_status));
    $verification_docs = function_exists('gb_get_cleaner_verification_documents') ? gb_get_cleaner_verification_documents($pid) : [];
?>
    <form method="post">
        <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="gb_save_action" value="save_cleaner">
        <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

        <?php if ($connect_notice === 'error' && $connect_notice_msg !== ''): ?>
            <div class="gb-card" style="margin-bottom:16px; background:#fef2f2; border-color:#fee2e2; color:#991b1b; padding:12px 14px;">
                Stripe Connect: <?php echo esc_html($connect_notice_msg); ?>
            </div>
        <?php elseif (in_array($connect_notice, ['returned', 'synced'], true)): ?>
            <div class="gb-card" style="margin-bottom:16px; background:#f0fdf4; border-color:#dcfce7; color:#166534; padding:12px 14px;">
                Stripe Connect <?php echo $connect_notice === 'synced' ? 'status synced.' : 'onboarding returned successfully.'; ?>
            </div>
        <?php endif; ?>
        
        <div class="gb-edit-header" style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px;">
            <div>
                <a href="?tab=cleaners" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:12px;">← BACK TO PROFESSIONALS</a>
                <h1 class="gb-title" style="margin:8px 0 0; font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.8px;">Manage Profile</h1>
                <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Update cleaner profile content, subscription status, and performance data.</p>
            </div>
            <div style="display:flex; gap:12px;">
                <select name="post_status" class="gb-os-input" style="width:170px;">
                    <option value="publish" <?php selected($post->post_status, 'publish'); ?>>Verified (Live)</option>
                    <option value="pending" <?php selected($post->post_status, 'pending'); ?>>Pending Approval</option>
                </select>
                <button type="submit" class="gb-header-btn" style="background:#2e7d32; color:#fff; border:none; padding:0 24px; height:42px; border-radius:8px; font-weight:700; cursor:pointer;">Save Changes</button>
            </div>
        </div>

        <div class="gb-os-grid">
            
            <div class="gb-os-main">
                <div class="gb-identity-block">
                    <?php if($image_url): ?><img src="<?php echo esc_url($image_url); ?>" class="gb-avatar-large"><?php else: ?><div class="gb-avatar-large" style="display:flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-camera" style="font-size:28px; color:#cbd5e1;"></span></div><?php endif; ?>
                    <div style="flex-grow:1;">
                        <label class="gb-os-label">Display Name</label>
                        <input type="text" name="post_title" class="gb-os-input" style="font-size:18px; font-weight:700;" value="<?php echo esc_attr($post->post_title); ?>">
                    </div>
                </div>

                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title"><span class="dashicons dashicons-editor-alignleft"></span> Experience & About</div></div></div>
                    <div class="gb-os-body">
                        <?php wp_editor($post->post_content, 'post_content', ['textarea_rows' => 8, 'media_buttons' => false]); ?>
                    </div>
                </div>

                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title"><span class="dashicons dashicons-list-view"></span> Billing Record</div></div></div>
                    <div style="padding:0;">
                        <table class="gb-table-os">
                            <thead><tr><th>Date</th><th>Type</th><th>Total</th><th style="text-align:right;">Status</th></tr></thead>
                            <tbody>
                                <tr><td>Dec 20, 2025</td><td style="color:#64748b;">Monthly Professional Fee</td><td>£25.00</td><td style="text-align:right;"><span class="pill-active">Paid</span></td></tr>
                                <tr><td>Nov 20, 2025</td><td style="color:#64748b;">Monthly Professional Fee</td><td>£25.00</td><td style="text-align:right;"><span class="pill-active">Paid</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="gb-os-sidebar">
                <div class="gb-os-card" style="border-top: 3px solid #10b981;">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Subscription</div></div></div>
                    <div class="gb-os-body">
                        <div style="margin-bottom:15px;">
                            <label class="gb-os-label">Account Type</label>
                            <select name="_gb_subscription_status" class="gb-os-input">
                                <option value="trial" <?php selected($sub_st, 'trial'); ?>>Free Trial</option>
                                <option value="active" <?php selected($sub_st, 'active'); ?>>Active Member</option>
                            </select>
                        </div>
                        <label class="gb-os-label">Next Renewal</label>
                        <input type="date" name="_gb_sub_renewal" class="gb-os-input" value="<?php echo esc_attr(get_post_meta($pid, '_gb_sub_renewal', true)); ?>">
                    </div>
                </div>

                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Verification Documents</div></div></div>
                    <div class="gb-os-body">
                        <?php if ($post->post_status === 'publish'): ?>
                            <div style="font-size:12px; color:#166534; background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; padding:12px;">
                                This cleaner is verified. For data protection, the ID, proof-of-address and DBS documents they uploaded for review are automatically and permanently deleted once approval is granted, so sensitive files are never kept on the platform longer than needed.
                            </div>
                        <?php else: ?>
                            <?php foreach (['doc_id' => 'Government ID', 'doc_address' => 'Proof of Address', 'doc_dbs' => 'DBS Certificate'] as $doc_key => $doc_label): ?>
                                <?php
                                $doc = isset($verification_docs[$doc_key]) && is_array($verification_docs[$doc_key]) ? $verification_docs[$doc_key] : [];
                                $doc_url = function_exists('gb_get_cleaner_verification_document_url') ? gb_get_cleaner_verification_document_url($pid, $doc_key) : '';
                                $uploaded_at = !empty($doc['uploaded_at']) ? date_i18n('M j, Y g:i a', strtotime((string) $doc['uploaded_at'])) : '';
                                $storage_label = !empty($doc['storage']) && $doc['storage'] === 'legacy' ? 'Legacy media upload' : 'Private storage';
                                ?>
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #f1f5f9;">
                                    <div>
                                        <div style="font-weight:700; color:#0f172a;"><?php echo esc_html($doc_label); ?></div>
                                        <?php if (!empty($doc['status']) && $doc['status'] === 'available'): ?>
                                            <div style="font-size:11px; color:#64748b;">
                                                <?php echo esc_html($storage_label); ?><?php echo $uploaded_at ? ' · Uploaded ' . esc_html($uploaded_at) : ''; ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size:11px; color:#94a3b8;">No document on file yet.</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($doc_url !== '' && !empty($doc['status']) && $doc['status'] === 'available'): ?>
                                        <a href="<?php echo esc_url($doc_url); ?>" target="_blank" rel="noopener" class="gb-btn-small-os" style="justify-content:center; white-space:nowrap;">View</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div style="font-size:11px; color:#64748b; margin-top:12px;">
                                Uploaded verification files (ID, proof of address, DBS) are kept in private storage — never the public media library — and are automatically and permanently deleted once the cleaner is marked verified.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                $v_phone      = get_post_meta($pid, '_gb_cleaner_phone', true);
                $v_ref1_name  = get_post_meta($pid, '_gb_ref1_name', true);
                $v_ref1_addr  = get_post_meta($pid, '_gb_ref1_address', true);
                $v_ref1_email = get_post_meta($pid, '_gb_ref1_email', true);
                $v_ref2_name  = get_post_meta($pid, '_gb_ref2_name', true);
                $v_ref2_addr  = get_post_meta($pid, '_gb_ref2_address', true);
                $v_ref2_email = get_post_meta($pid, '_gb_ref2_email', true);
                ?>
                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Phone &amp; Character References</div></div></div>
                    <div class="gb-os-body">
                        <div style="padding:6px 0 12px; border-bottom:1px solid #f1f5f9;">
                            <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700;">Cleaner Phone</div>
                            <div style="font-weight:700; color:#0f172a;"><?php echo $v_phone ? esc_html($v_phone) : '<span style="color:#ef4444; font-weight:600;">Not provided</span>'; ?></div>
                        </div>
                        <?php foreach ([['1', $v_ref1_name, $v_ref1_addr, $v_ref1_email], ['2', $v_ref2_name, $v_ref2_addr, $v_ref2_email]] as $ref):
                            list($rn, $r_name, $r_addr, $r_email) = $ref; ?>
                            <div style="padding:12px 0; border-bottom:1px solid #f1f5f9;">
                                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:6px;">Reference <?php echo esc_html($rn); ?></div>
                                <?php if ($r_name || $r_addr || $r_email): ?>
                                    <div style="font-weight:700; color:#0f172a;"><?php echo esc_html($r_name ?: '—'); ?></div>
                                    <div style="font-size:13px; color:#475569;"><?php echo esc_html($r_addr ?: '—'); ?></div>
                                    <div style="font-size:13px; color:#475569;"><?php echo esc_html($r_email ?: '—'); ?></div>
                                <?php else: ?>
                                    <div style="color:#ef4444; font-size:13px; font-weight:600;">Not provided</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php
                $toa_signed  = get_post_meta($pid, '_gb_toa_signed', true) === '1';
                $toa_name    = get_post_meta($pid, '_gb_toa_signed_name', true);
                $toa_title   = get_post_meta($pid, '_gb_toa_signed_title', true);
                $toa_date    = get_post_meta($pid, '_gb_toa_signed_date', true);
                ?>
                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Terms of Association</div></div></div>
                    <div class="gb-os-body">
                        <?php if ($toa_signed): ?>
                            <div style="font-weight:700; color:#166534;">✅ Signed</div>
                            <div style="font-size:13px; color:#475569; margin-top:4px;">
                                <?php echo esc_html($toa_name); ?> (<?php echo esc_html($toa_title); ?>)<?php echo $toa_date ? ' · ' . esc_html(date_i18n('j M Y', strtotime($toa_date))) : ''; ?>
                            </div>
                            <a href="<?php echo esc_url(add_query_arg('agreement_id', $pid, home_url('/manager-dashboard'))); ?>" target="_blank" rel="noopener" class="gb-btn-small-os" style="margin-top:12px; justify-content:center;">Print / Download Agreement</a>
                        <?php else: ?>
                            <div style="color:#ef4444; font-weight:600; font-size:13px;">Not signed yet</div>
                            <div style="font-size:12px; color:#94a3b8; margin-top:4px;">The cleaner must sign before they can be verified.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Stripe Connect</div></div></div>
                    <div class="gb-os-body">
                        <div style="margin-bottom:12px;">
                            <label class="gb-os-label">Connect Status</label>
                            <div style="font-weight:700; color:#0f172a;"><?php echo esc_html($connect_status_label); ?></div>
                            <div style="font-size:11px; color:#64748b; margin-top:4px;">Environment: <?php echo $stripe_mode === 'live' ? 'Live' : 'Test (Sandbox)'; ?></div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; font-size:12px;">
                            <div>Charges: <strong><?php echo $stripe_charges_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
                            <div>Payouts: <strong><?php echo $stripe_payouts_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
                            <div>Details: <strong><?php echo $stripe_details_submitted ? 'Submitted' : 'Missing'; ?></strong></div>
                            <div>Requirements: <strong><?php echo (int) $stripe_requirements_due_count; ?> due</strong></div>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label class="gb-os-label">Connected Account ID</label>
                            <input type="text" class="gb-os-input" value="<?php echo esc_attr($stripe_account_id ?: 'Not connected yet'); ?>" readonly>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label class="gb-os-label">Payout Schedule</label>
                            <select name="_gb_stripe_payout_interval" class="gb-os-input">
                                <option value="daily" <?php selected($payout_interval, 'daily'); ?>>Daily</option>
                                <option value="manual" <?php selected($payout_interval, 'manual'); ?>>Manual</option>
                                <option value="weekly" <?php selected($payout_interval, 'weekly'); ?>>Weekly</option>
                                <option value="monthly" <?php selected($payout_interval, 'monthly'); ?>>Monthly</option>
                            </select>
                        </div>

                        <?php if ($stripe_last_sync): ?>
                            <div style="font-size:11px; color:#64748b; margin-bottom:10px;">Last synced: <?php echo esc_html(date_i18n('M j, Y g:i a', strtotime($stripe_last_sync))); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($stripe_last_error)): ?>
                            <div style="font-size:11px; color:#b91c1c; margin-bottom:10px;"><?php echo esc_html($stripe_last_error); ?></div>
                        <?php endif; ?>

                        <div style="display:grid; gap:8px;">
                            <a href="<?php echo esc_url($connect_onboard_url); ?>" class="gb-btn-small-os" style="justify-content:center;"><?php echo $stripe_account_id ? 'Continue Onboarding' : 'Start Onboarding'; ?></a>
                            <?php if ($stripe_account_id): ?>
                                <a href="<?php echo esc_url($connect_login_url); ?>" class="gb-btn-small-os" style="justify-content:center;">Open Express Dashboard</a>
                                <a href="<?php echo esc_url($connect_sync_url); ?>" class="gb-btn-small-os" style="justify-content:center;">Sync Stripe Status</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Service Details</div></div></div>
                    <div class="gb-os-body">
                        <div style="margin-bottom:15px;">
                            <label class="gb-os-label">Hourly Rate (£)</label>
                            <input type="number" name="hourly_rate" class="gb-os-input" value="<?php echo esc_attr($rate); ?>">
                        </div>
                        <div style="margin-bottom:15px;">
                            <label class="gb-os-label">Location</label>
                            <input type="text" name="_gb_location" class="gb-os-input" value="<?php echo esc_attr($loc); ?>">
                        </div>
                        <label class="gb-os-label">Experience</label>
                        <input type="text" name="_gb_experience" class="gb-os-input" value="<?php echo esc_attr($exp); ?>">
                    </div>
                </div>

                <div class="gb-os-card">
                    <div class="gb-os-head"><div class="gb-os-head-content"><div class="gb-os-title">Performance Stats</div></div></div>
                    <div class="gb-os-body">
                        <div style="margin-bottom:15px;">
                            <label class="gb-os-label">Availability Status</label>
                            <select name="_gb_availability" class="gb-os-input">
                                <option value="Available" <?php selected($avail, 'Available'); ?>>Available</option>
                                <option value="Booked" <?php selected($avail, 'Booked'); ?>>Fully Booked</option>
                            </select>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div><label class="gb-os-label">Rating</label><input type="number" step="0.1" name="_gb_rating" class="gb-os-input" value="<?php echo esc_attr($rating); ?>"></div>
                            <div><label class="gb-os-label">Reviews</label><input type="number" name="_gb_review_count" class="gb-os-input" value="<?php echo esc_attr($reviews); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php } else {
    // --- LIST VIEW (Full Table Aligned) ---
?>
    <div class="gb-edit-header" style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px;">
        <div>
            <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; margin:0; letter-spacing:-1px;">Fleet Management</h1>
            <p style="color: #64748b; margin-top: 6px; font-size: 15px;">Review professionals and active subscription data.</p>
        </div>
        <a href="?tab=cleaners&action=edit&id=0" class="gb-btn-primary-os" style="background:#2e7d32; color:#fff; text-decoration:none; padding:10px 20px; border-radius:8px; font-weight:700; font-size:14px;">+ Add Professional</a>
    </div>

    <div class="gb-os-card" style="padding:0; overflow:hidden;">
        <table class="gb-table-os">
            <thead>
                <tr>
                    <th style="width:60px;"></th>
                    <th>Name / Location</th>
                    <th>Subscription</th>
                    <th>Rate</th>
                    <th>Payments</th>
                    <th>Verification</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $cleaners = new WP_Query(['post_type' => 'gb_cleaner', 'post_status' => ['publish', 'pending', 'draft'], 'posts_per_page' => 20]);
                if($cleaners->have_posts()): while($cleaners->have_posts()): $cleaners->the_post(); 
                    $cid = get_the_ID(); $st = get_post_status(); $rate = get_post_meta($cid, 'hourly_rate', true) ?: '25';
                    $sub = get_post_meta($cid, '_gb_subscription_status', true) ?: 'trial';
                    $thumb = get_the_post_thumbnail_url($cid, 'thumbnail');
                    $connect_status = get_post_meta($cid, '_gb_stripe_connect_status', true) ?: 'not_connected';
                    $charges_enabled = get_post_meta($cid, '_gb_stripe_charges_enabled', true) === '1';
                    $payouts_enabled = get_post_meta($cid, '_gb_stripe_payouts_enabled', true) === '1';
                    $loc = get_post_meta($cid, '_gb_location', true);
                    if ($loc === '') {
                        $loc = get_post_meta($cid, 'cleaner_location', true);
                    }
                    if ($loc === '') {
                        $loc = get_post_meta($cid, 'location', true);
                    }
                    if ($loc === '') {
                        $loc = 'UK Base';
                    }
                ?>
                <tr>
                    <td>
                        <?php if($thumb): ?><img src="<?php echo esc_url($thumb); ?>" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0;"><?php else: ?><div style="width:36px; height:36px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; border:1px solid #e2e8f0;"><span class="dashicons dashicons-admin-users" style="color:#cbd5e1; font-size:18px;"></span></div><?php endif; ?>
                    </td>
                    <td>
                        <strong style="color: #1b5e20; font-size: 14px;"><?php the_title(); ?></strong>
                        <div style="font-size:11px; color:#94a3b8;"><?php echo esc_html($loc); ?></div>
                    </td>
                    <td><span class="<?php echo ($sub === 'active') ? 'pill-active' : 'pill-trial'; ?>"><?php echo esc_html($sub); ?></span></td>
                    <td style="font-weight:700; color:#0f172a;">£<?php echo esc_html($rate); ?>/hr</td>
                    <td>
                        <?php if ($connect_status === 'active' && $charges_enabled): ?>
                            <span class="pill-active">Ready</span>
                        <?php elseif ($connect_status === 'not_connected'): ?>
                            <span class="pill-trial" style="background:#f1f5f9; color:#64748b;">Not Connected</span>
                        <?php else: ?>
                            <span class="pill-trial"><?php echo esc_html(ucwords(str_replace('_', ' ', $connect_status))); ?></span>
                        <?php endif; ?>
                        <?php if ($charges_enabled && !$payouts_enabled): ?>
                            <div style="font-size:10px; color:#b45309; margin-top:4px;">Payouts pending</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($st == 'publish'): ?>
                            <span class="pill-verified"><img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-icon-logo.png" style="height:11px;"> Verified</span>
                        <?php else: ?>
                            <span class="pill-trial" style="background:#f1f5f9; color:#64748b;"><?php echo strtoupper($st); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex; justify-content:flex-end; gap:8px;">
                            <a href="?tab=cleaners&action=edit&id=<?php echo $cid; ?>" style="background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 14px; font-weight: 700; color: #334155; text-decoration: none; font-size: 11px;">Edit Profile</a>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete_entity', 'id' => $cid], home_url('/manager-dashboard')), 'gb_manager_action_delete_entity_' . $cid)); ?>" style="background: white; border: 1px solid #fecaca; border-radius: 6px; padding: 6px 14px; font-weight: 700; color: #ef4444; text-decoration: none; font-size: 11px;" onclick="return confirm('Delete this cleaner profile? It will be moved to trash.');">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; wp_reset_postdata(); endif; ?>
            </tbody>
        </table>
    </div>
<?php } ?>
