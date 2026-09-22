<?php
/* Template Name: Cleaner Verification */

if ( ! is_user_logged_in() ) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$cleaner_post = get_posts([
    'post_type' => 'gb_cleaner',
    'author' => $current_user->ID,
    'posts_per_page' => 1,
    'post_status' => ['publish', 'pending', 'draft'],
]);

if (empty($cleaner_post)) wp_die("No cleaner profile found.");
$cleaner_id = $cleaner_post[0]->ID;
$status = $cleaner_post[0]->post_status;

$msg = '';

// Helper: is a verification document already stored?
$gb_doc_present = function($field) use ($cleaner_id) {
    if (class_exists('GB_Uploads') && method_exists('GB_Uploads', 'get_cleaner_verification_document')) {
        $doc = GB_Uploads::get_cleaner_verification_document($cleaner_id, $field);
        return !empty($doc);
    }
    return (bool) get_post_meta($cleaner_id, '_gb_' . $field, true);
};

// --- HANDLE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_upload_docs']) && wp_verify_nonce($_POST['verify_nonce'], 'gb_verify_action')) {
    $rate_state = gb_rate_limit_check('cleaner_verification_upload', 5, HOUR_IN_SECONDS, (string) $cleaner_id);
    if ($rate_state['blocked']) {
        $msg .= '<div class="gb-alert error">' . esc_html(gb_get_rate_limit_message($rate_state)) . '</div>';
    } else {
        gb_record_rate_limit_hit('cleaner_verification_upload', HOUR_IN_SECONDS, (string) $cleaner_id);

        // 1. Save text fields (persist progress even if submission is incomplete).
        $text_fields = [
            '_gb_cleaner_phone' => 'cleaner_phone',
            '_gb_ref1_name'     => 'ref1_name',
            '_gb_ref1_address'  => 'ref1_address',
            '_gb_ref1_email'    => 'ref1_email',
            '_gb_ref2_name'     => 'ref2_name',
            '_gb_ref2_address'  => 'ref2_address',
            '_gb_ref2_email'    => 'ref2_email',
        ];
        foreach ($text_fields as $meta_key => $post_key) {
            $val = isset($_POST[$post_key]) ? sanitize_text_field(wp_unslash($_POST[$post_key])) : '';
            update_post_meta($cleaner_id, $meta_key, $val);
        }

        // 2. Process document uploads.
        if (class_exists('GB_Uploads') && method_exists('GB_Uploads', 'store_cleaner_verification_document')) {
            foreach (['doc_id', 'doc_address', 'doc_dbs'] as $field) {
                if (!empty($_FILES[$field]['name'])) {
                    $upload_result = GB_Uploads::store_cleaner_verification_document($cleaner_id, $field, $_FILES[$field]);
                    if (is_wp_error($upload_result)) {
                        $msg .= '<div class="gb-alert error">Error uploading document: ' . esc_html($upload_result->get_error_message()) . '</div>';
                    }
                }
            }
        } else {
            $msg .= '<div class="gb-alert error">Secure upload handling is unavailable right now. Please contact support.</div>';
        }

        // 3. Capture Terms of Association e-signature.
        $agree      = !empty($_POST['toa_agree']);
        $sign_name  = isset($_POST['toa_name'])  ? sanitize_text_field(wp_unslash($_POST['toa_name']))  : '';
        $sign_title = isset($_POST['toa_title']) ? sanitize_text_field(wp_unslash($_POST['toa_title'])) : '';
        if ($agree && $sign_name && $sign_title) {
            update_post_meta($cleaner_id, '_gb_toa_signed', '1');
            update_post_meta($cleaner_id, '_gb_toa_signed_name', $sign_name);
            update_post_meta($cleaner_id, '_gb_toa_signed_title', $sign_title);
            update_post_meta($cleaner_id, '_gb_toa_signed_date', current_time('mysql'));
            update_post_meta($cleaner_id, '_gb_toa_signed_version', gb_get_terms_of_association_version());
            update_post_meta($cleaner_id, '_gb_toa_signed_ip', isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
            update_post_meta($cleaner_id, '_gb_toa_signed_text', wp_kses_post(gb_get_terms_of_association()));
        }

        // 4. Hard-block completeness check (docs + phone + references + signature).
        $missing = function_exists('gb_cleaner_verification_missing')
            ? gb_cleaner_verification_missing($cleaner_id)
            : [];

        if (empty($missing)) {
            wp_update_post(['ID' => $cleaner_id, 'post_status' => 'pending']);
            gb_sync_cleaner_verification_state($cleaner_id, 'pending');
            $status = 'pending';
            $msg = '<div class="gb-alert success">✅ Everything received. Your profile is now under review.</div>';
        } else {
            $msg .= '<div class="gb-alert error"><strong>Your progress is saved, but you cannot be verified yet.</strong><br>Still required: ' . esc_html(implode(', ', $missing)) . '</div>';
        }
    }
}

// Prefill values
$v_phone       = get_post_meta($cleaner_id, '_gb_cleaner_phone', true);
$v_ref1_name   = get_post_meta($cleaner_id, '_gb_ref1_name', true);
$v_ref1_addr   = get_post_meta($cleaner_id, '_gb_ref1_address', true);
$v_ref1_email  = get_post_meta($cleaner_id, '_gb_ref1_email', true);
$v_ref2_name   = get_post_meta($cleaner_id, '_gb_ref2_name', true);
$v_ref2_addr   = get_post_meta($cleaner_id, '_gb_ref2_address', true);
$v_ref2_email  = get_post_meta($cleaner_id, '_gb_ref2_email', true);

$toa_signed      = get_post_meta($cleaner_id, '_gb_toa_signed', true) === '1';
$toa_signed_name = get_post_meta($cleaner_id, '_gb_toa_signed_name', true) ?: $cleaner_post[0]->post_title;
$toa_signed_title= get_post_meta($cleaner_id, '_gb_toa_signed_title', true) ?: 'Cleaner';
$toa_signed_date = get_post_meta($cleaner_id, '_gb_toa_signed_date', true);
$toa_text        = function_exists('gb_get_terms_of_association') ? gb_get_terms_of_association() : '';

get_header();
?>

<style>
    #gb-page-wrapper { width: 100vw; position: relative; left: 50%; margin-left: -50vw; background: #f4f6f8; min-height: 100vh; font-family: sans-serif; }
    #gb-page-wrapper .gb-hero-sm { background: #0f172a; padding: 60px 0 100px; color: white; text-align: center; }
    #gb-page-wrapper .gb-container { max-width: 640px; margin: -50px auto 50px; position: relative; padding: 0 20px; }
    #gb-page-wrapper .gb-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }

    #gb-page-wrapper .gb-back-link { display: inline-flex; align-items: center; color: white; text-decoration: none; font-weight: 600; margin-bottom: 20px; opacity: 0.8; }
    #gb-page-wrapper .gb-back-link:hover { opacity: 1; text-decoration: underline; }

    #gb-page-wrapper .gb-alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; line-height: 1.5; }
    #gb-page-wrapper .gb-alert.success { background: #dcfce7; color: #166534; }
    #gb-page-wrapper .gb-alert.error { background: #fee2e2; color: #991b1b; }

    #gb-page-wrapper .gb-section-head { font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 30px 0 12px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
    #gb-page-wrapper .gb-upload-box { border: 2px dashed #cbd5e1; padding: 18px; border-radius: 8px; text-align: center; margin-bottom: 16px; transition: 0.2s; background: #f8fafc; }
    #gb-page-wrapper .gb-upload-box.done { border-color: #10b981; background: #f0fdf4; }
    #gb-page-wrapper .gb-upload-label { font-weight: 700; display: block; margin-bottom: 6px; color: #334155; }
    #gb-page-wrapper input[type="file"] { width: 100%; }
    #gb-page-wrapper .gb-field { margin-bottom: 14px; }
    #gb-page-wrapper .gb-field label { display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:5px; }
    #gb-page-wrapper .gb-field input { width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; box-sizing:border-box; font-family:inherit; }
    #gb-page-wrapper .gb-ref-card { border:1px solid #e2e8f0; border-radius:10px; padding:18px; margin-bottom:16px; background:#f8fafc; }
    #gb-page-wrapper .gb-req { color:#ef4444; }
</style>

<div id="gb-page-wrapper">
    <div class="gb-hero-sm">
        <div style="max-width:640px; margin:0 auto; text-align:left; padding:0 20px;">
            <a href="/cleaner-dashboard" class="gb-back-link">← Back to Dashboard</a>
        </div>
        <h1>Get Verified</h1>
        <p>All items below are required before you can go live and accept jobs.</p>
    </div>

    <div class="gb-container">

        <?php echo $msg; ?>

        <div class="gb-card">
            <?php
            /*
             * "Verified" here has to mean the same thing it means to a customer.
             * This page used to declare it from post status alone, so a cleaner
             * whose profile was live but still had a check outstanding was told
             * "You are Verified! No further action is needed" while the public
             * badge was correctly withheld - and no screen told them why.
             */
            $gb_fully  = function_exists('gb_cleaner_is_fully_verified') && gb_cleaner_is_fully_verified($cleaner_id);
            $gb_checks = function_exists('gb_get_cleaner_checks') ? gb_get_cleaner_checks($cleaner_id) : array();
            $gb_pending_checks = array();
            foreach (array('id' => 'Photo ID', 'dbs' => 'Basic DBS certificate', 'refs' => 'Two references') as $gb_k => $gb_lbl) {
                if (isset($gb_checks[$gb_k]) && ! $gb_checks[$gb_k]) {
                    $gb_pending_checks[] = $gb_lbl;
                }
            }
            $gb_verified_on = function_exists('gb_get_cleaner_verified_date') ? gb_get_cleaner_verified_date($cleaner_id) : '';
            ?>
            <?php if($status === 'publish' && $gb_fully): ?>
                <div style="text-align:center; padding:40px 0;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:64px; color:#10b981; margin-bottom:20px; display:block; width:auto; height:auto;"></span>
                    <h2 style="margin:0; color:#10b981;">You are Verified!</h2>
                    <p style="color:#64748b;">No further action is needed.<?php echo $gb_verified_on !== '' ? '<br>Green Broom Verified &mdash; ' . esc_html($gb_verified_on) : ''; ?></p>
                    <a href="/cleaner-dashboard" style="display:inline-block; margin-top:20px; background:#0f172a; color:white; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:600;">Go to Dashboard</a>
                </div>
            <?php elseif($status === 'publish'): ?>
                <div style="text-align:center; padding:34px 0 10px;">
                    <span class="dashicons dashicons-clock" style="font-size:60px; color:#f59e0b; margin-bottom:18px; display:block; width:auto; height:auto;"></span>
                    <h2 style="margin:0; color:#b45309;">Your profile is live, with checks outstanding</h2>
                    <p style="color:#64748b; max-width:520px; margin:12px auto 0;">
                        Customers can find and book you. The Green Broom Verified badge stays hidden on your
                        profile until our team has confirmed every check below.
                    </p>
                    <?php if (!empty($gb_pending_checks)): ?>
                        <div style="display:inline-block; text-align:left; margin-top:18px; padding:14px 18px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px;">
                            <strong style="display:block; font-size:13px; color:#92400e; margin-bottom:6px;">Still to confirm</strong>
                            <ul style="margin:0; padding-left:18px; color:#92400e; font-size:14px;">
                                <?php foreach ($gb_pending_checks as $gb_pc): ?><li><?php echo esc_html($gb_pc); ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <p style="color:#94a3b8; font-size:13px; margin-top:16px;">
                        Already sent these? Our team confirms them manually &mdash; nothing more is needed from you.
                        You can update your details below if anything has changed.
                    </p>
                </div>
            <?php elseif($status === 'pending'): ?>
                <div style="text-align:center; padding:30px 0 20px;">
                    <span class="dashicons dashicons-hourglass" style="font-size:64px; color:#f59e0b; margin-bottom:20px; display:block; width:auto; height:auto;"></span>
                    <h2 style="margin:0; color:#f59e0b;">Under Review</h2>
                    <p style="color:#64748b;">Our team is reviewing your documents and references.<br>This usually takes 24-48 hours.</p>
                    <hr style="border:0; border-top:1px solid #eee; margin:24px 0;">
                    <p style="font-size:14px; color:#999;">Need to update something? Edit and resubmit below.</p>
                </div>
            <?php endif; ?>

            <?php if($status !== 'publish' || !$gb_fully): ?>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('gb_verify_action', 'verify_nonce'); ?>
                    <input type="hidden" name="gb_upload_docs" value="1">

                    <div class="gb-section-head">Your Contact Details</div>
                    <div class="gb-field">
                        <label>Your Phone Number <span class="gb-req">*</span></label>
                        <input type="tel" name="cleaner_phone" value="<?php echo esc_attr($v_phone); ?>" placeholder="e.g. 07123 456789" required>
                    </div>

                    <div class="gb-section-head">Documents</div>
                    <div class="gb-upload-box <?php echo $gb_doc_present('doc_id') ? 'done' : ''; ?>">
                        <label class="gb-upload-label">Government ID <span class="gb-req">*</span></label>
                        <p style="font-size:12px; color:#94a3b8; margin:0 0 10px;">Passport or Driving License (JPG/PNG/PDF)<?php echo $gb_doc_present('doc_id') ? ' — ✅ on file (upload again to replace)' : ''; ?></p>
                        <input type="file" name="doc_id" accept="image/*,.pdf" <?php echo $gb_doc_present('doc_id') ? '' : 'required'; ?>>
                    </div>
                    <div class="gb-upload-box <?php echo $gb_doc_present('doc_address') ? 'done' : ''; ?>">
                        <label class="gb-upload-label">Proof of Address <span class="gb-req">*</span></label>
                        <p style="font-size:12px; color:#94a3b8; margin:0 0 10px;">Utility Bill or Bank Statement (recent)<?php echo $gb_doc_present('doc_address') ? ' — ✅ on file (upload again to replace)' : ''; ?></p>
                        <input type="file" name="doc_address" accept="image/*,.pdf" <?php echo $gb_doc_present('doc_address') ? '' : 'required'; ?>>
                    </div>
                    <div class="gb-upload-box <?php echo $gb_doc_present('doc_dbs') ? 'done' : ''; ?>">
                        <label class="gb-upload-label">DBS Certificate <span class="gb-req">*</span></label>
                        <p style="font-size:12px; color:#94a3b8; margin:0 0 10px;">Your Disclosure &amp; Barring Service certificate (JPG/PNG/PDF)<?php echo $gb_doc_present('doc_dbs') ? ' — ✅ on file (upload again to replace)' : ''; ?></p>
                        <input type="file" name="doc_dbs" accept="image/*,.pdf" <?php echo $gb_doc_present('doc_dbs') ? '' : 'required'; ?>>
                    </div>

                    <div class="gb-section-head">Character References</div>
                    <p style="font-size:13px; color:#64748b; margin:0 0 16px;">Provide two verifiable references. We may contact them to confirm your suitability.</p>

                    <div class="gb-ref-card">
                        <div style="font-weight:800; color:#0f172a; margin-bottom:12px;">Reference 1</div>
                        <div class="gb-field"><label>Full Name <span class="gb-req">*</span></label><input type="text" name="ref1_name" value="<?php echo esc_attr($v_ref1_name); ?>" required></div>
                        <div class="gb-field"><label>Address <span class="gb-req">*</span></label><input type="text" name="ref1_address" value="<?php echo esc_attr($v_ref1_addr); ?>" required></div>
                        <div class="gb-field" style="margin-bottom:0;"><label>Email Address <span class="gb-req">*</span></label><input type="email" name="ref1_email" value="<?php echo esc_attr($v_ref1_email); ?>" required></div>
                    </div>
                    <div class="gb-ref-card">
                        <div style="font-weight:800; color:#0f172a; margin-bottom:12px;">Reference 2</div>
                        <div class="gb-field"><label>Full Name <span class="gb-req">*</span></label><input type="text" name="ref2_name" value="<?php echo esc_attr($v_ref2_name); ?>" required></div>
                        <div class="gb-field"><label>Address <span class="gb-req">*</span></label><input type="text" name="ref2_address" value="<?php echo esc_attr($v_ref2_addr); ?>" required></div>
                        <div class="gb-field" style="margin-bottom:0;"><label>Email Address <span class="gb-req">*</span></label><input type="email" name="ref2_email" value="<?php echo esc_attr($v_ref2_email); ?>" required></div>
                    </div>

                    <div class="gb-section-head">Terms of Association <span class="gb-req">*</span></div>
                    <?php if ($toa_signed): ?>
                        <div class="gb-alert success" style="margin-bottom:16px;">✅ Signed by <?php echo esc_html($toa_signed_name); ?> (<?php echo esc_html($toa_signed_title); ?>)<?php echo $toa_signed_date ? ' on ' . esc_html(date_i18n('j M Y', strtotime($toa_signed_date))) : ''; ?>. Re-sign below only if you need to update it.</div>
                    <?php endif; ?>
                    <div style="max-height:220px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; padding:16px; background:#f8fafc; font-size:13px; line-height:1.6; color:#334155; white-space:pre-wrap; margin-bottom:16px;"><?php echo wp_kses_post($toa_text); ?></div>
                    <div class="gb-field"><label>Type your full name <span class="gb-req">*</span></label><input type="text" name="toa_name" value="<?php echo esc_attr($toa_signed ? $toa_signed_name : $cleaner_post[0]->post_title); ?>" <?php echo $toa_signed ? '' : 'required'; ?>></div>
                    <div class="gb-field"><label>Your title / role <span class="gb-req">*</span></label><input type="text" name="toa_title" value="<?php echo esc_attr($toa_signed ? $toa_signed_title : 'Cleaner'); ?>" placeholder="e.g. Cleaner, Sole Trader" <?php echo $toa_signed ? '' : 'required'; ?>></div>
                    <label style="display:flex; gap:10px; align-items:flex-start; font-size:13px; color:#334155; margin:6px 0 4px; cursor:pointer;">
                        <input type="checkbox" name="toa_agree" value="1" style="margin-top:3px;" <?php echo $toa_signed ? 'checked' : 'required'; ?>>
                        <span>I confirm I have read and agree to the Terms of Association above, and that typing my name constitutes my legal signature.</span>
                    </label>

                    <button type="submit" style="width:100%; background:#10b981; color:white; border:none; padding:15px; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer; margin-top:16px;">Submit for Verification</button>
                    <p style="text-align:center; font-size:12px; color:#94a3b8; margin:14px 0 0;">You will not go live until all documents and references are provided and approved.</p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>
