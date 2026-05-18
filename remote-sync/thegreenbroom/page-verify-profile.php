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

// --- HANDLE FILE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gb_upload_docs']) && wp_verify_nonce($_POST['verify_nonce'], 'gb_verify_action')) {
    if (!class_exists('GB_Uploads') || !method_exists('GB_Uploads', 'store_cleaner_verification_document')) {
        $msg .= '<div class="gb-alert error">Secure upload handling is unavailable right now. Please contact support.</div>';
    } else {
        $rate_state = gb_rate_limit_check('cleaner_verification_upload', 5, HOUR_IN_SECONDS, (string) $cleaner_id);
        if ($rate_state['blocked']) {
            $msg .= '<div class="gb-alert error">' . esc_html(gb_get_rate_limit_message($rate_state)) . '</div>';
        } else {
            gb_record_rate_limit_hit('cleaner_verification_upload', HOUR_IN_SECONDS, (string) $cleaner_id);

            $files = ['doc_id', 'doc_address'];
            $success_count = 0;

            foreach ($files as $field) {
                if (!empty($_FILES[$field]['name'])) {
                    $upload_result = GB_Uploads::store_cleaner_verification_document($cleaner_id, $field, $_FILES[$field]);

                    if (is_wp_error($upload_result)) {
                        $msg .= '<div class="gb-alert error">Error uploading ' . esc_html($field) . ': ' . esc_html($upload_result->get_error_message()) . '</div>';
                    } else {
                        $success_count++;
                    }
                }
            }

            if ($success_count > 0) {
                wp_update_post(['ID' => $cleaner_id, 'post_status' => 'pending']);
                gb_sync_cleaner_verification_state($cleaner_id, 'pending');
                $msg = '<div class="gb-alert success">✅ Documents uploaded securely. Your profile is now under review.</div>';
                $status = 'pending';
            }
        }
    }
}

get_header(); 
?>

<style>
    #gb-page-wrapper { width: 100vw; position: relative; left: 50%; margin-left: -50vw; background: #f4f6f8; min-height: 100vh; font-family: sans-serif; }
    .gb-hero-sm { background: #0f172a; padding: 60px 0 100px; color: white; text-align: center; }
    .gb-container { max-width: 600px; margin: -50px auto 50px; position: relative; padding: 0 20px; }
    .gb-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    
    .gb-back-link { display: inline-flex; align-items: center; color: white; text-decoration: none; font-weight: 600; margin-bottom: 20px; opacity: 0.8; }
    .gb-back-link:hover { opacity: 1; text-decoration: underline; }

    .gb-alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
    .gb-alert.success { background: #dcfce7; color: #166534; }
    .gb-alert.error { background: #fee2e2; color: #991b1b; }

    .gb-upload-box { border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; transition: 0.2s; background: #f8fafc; }
    .gb-upload-box:hover { border-color: #10b981; background: #f0fdf4; }
    .gb-upload-label { font-weight: 700; display: block; margin-bottom: 8px; color: #334155; }
    
    input[type="file"] { width: 100%; }
</style>

<div id="gb-page-wrapper">
    <div class="gb-hero-sm">
        <div style="max-width:600px; margin:0 auto; text-align:left; padding:0 20px;">
            <a href="/cleaner-dashboard" class="gb-back-link">← Back to Dashboard</a>
        </div>
        <h1>Get Verified</h1>
        <p>Upload your documents securely to start accepting jobs.</p>
    </div>

    <div class="gb-container">
        
        <?php echo $msg; ?>

        <div class="gb-card">
            <?php if($status === 'publish'): ?>
                <div style="text-align:center; padding:40px 0;">
                    <span class="dashicons dashicons-yes-alt" style="font-size:64px; color:#10b981; margin-bottom:20px; display:block; width:auto; height:auto;"></span>
                    <h2 style="margin:0; color:#10b981;">You are Verified!</h2>
                    <p style="color:#64748b;">No further action is needed.</p>
                    <a href="/cleaner-dashboard" style="display:inline-block; margin-top:20px; background:#0f172a; color:white; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:600;">Go to Dashboard</a>
                </div>
            <?php elseif($status === 'pending'): ?>
                <div style="text-align:center; padding:40px 0;">
                    <span class="dashicons dashicons-hourglass" style="font-size:64px; color:#f59e0b; margin-bottom:20px; display:block; width:auto; height:auto;"></span>
                    <h2 style="margin:0; color:#f59e0b;">Under Review</h2>
                    <p style="color:#64748b;">Our team is currently reviewing your documents.<br>This usually takes 24-48 hours.</p>
                    
                    <hr style="border:0; border-top:1px solid #eee; margin:30px 0;">
                    <p style="font-size:14px; color:#999;">Need to re-upload?</p>
                </div>
            <?php endif; ?>

            <?php if($status !== 'publish'): ?>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('gb_verify_action', 'verify_nonce'); ?>
                    <input type="hidden" name="gb_upload_docs" value="1">

                    <div class="gb-upload-box">
                        <span class="dashicons dashicons-id-alt" style="font-size:32px; color:#64748b; margin-bottom:10px;"></span>
                        <label class="gb-upload-label">Government ID</label>
                        <p style="font-size:12px; color:#94a3b8; margin:0 0 10px;">Passport or Driving License (JPG/PNG/PDF)</p>
                        <input type="file" name="doc_id" required accept="image/*,.pdf">
                    </div>

                    <div class="gb-upload-box">
                        <span class="dashicons dashicons-admin-home" style="font-size:32px; color:#64748b; margin-bottom:10px;"></span>
                        <label class="gb-upload-label">Proof of Address</label>
                        <p style="font-size:12px; color:#94a3b8; margin:0 0 10px;">Utility Bill or Bank Statement (Recent)</p>
                        <input type="file" name="doc_address" required accept="image/*,.pdf">
                    </div>

                    <button type="submit" style="width:100%; background:#10b981; color:white; border:none; padding:15px; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer;">Submit for Verification</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>
