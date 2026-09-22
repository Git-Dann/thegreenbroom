<?php
/* Template Name: Manager Dashboard (Green OS) */

function gb_manager_sanitize_meta_value($value) {
    if (is_array($value)) {
        return array_map('gb_manager_sanitize_meta_value', $value);
    }

    return wp_kses_post(wp_unslash($value));
}

if (
    isset($_GET['tab'], $_GET['action'], $_SERVER['REQUEST_METHOD'])
    && $_SERVER['REQUEST_METHOD'] === 'GET'
    && sanitize_key(wp_unslash($_GET['tab'])) === 'blog'
    && sanitize_key(wp_unslash($_GET['action'])) === 'edit'
) {
    $legacy_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $legacy_url = $legacy_id > 0
        ? home_url('/manager-dashboard/?tab=blog&view=edit&id=' . $legacy_id)
        : home_url('/manager-dashboard/?tab=blog');

    wp_safe_redirect($legacy_url);
    exit;
}

// 1. SECURITY & SETUP
$current_user = wp_get_current_user();
if (!in_array('gb_manager', (array) $current_user->roles) && !current_user_can('administrator')) {
    wp_redirect(home_url('/login')); exit;
}
add_filter('show_admin_bar', '__return_false');

// Required for wp.media() (featured image picker, page-builder image fields,
// TinyMCE "Add Media") to work on this front-end template — without this,
// clicking those buttons does nothing because the media library JS/templates
// are never loaded outside wp-admin.
wp_enqueue_media();

$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
if ($tab === 'blog' && isset($_GET['view'])) {
    $action = sanitize_key($_GET['view']);
}
$msg = isset($_GET['msg']) ? sanitize_key($_GET['msg']) : '';

// Printable / downloadable signed Terms of Association (opens as a clean doc).
if (isset($_GET['agreement_id'])) {
    $agr_id = intval($_GET['agreement_id']);
    if (get_post_type($agr_id) === 'gb_cleaner' && get_post_meta($agr_id, '_gb_toa_signed', true) === '1') {
        $a_name    = get_post_meta($agr_id, '_gb_toa_signed_name', true);
        $a_title   = get_post_meta($agr_id, '_gb_toa_signed_title', true);
        $a_date    = get_post_meta($agr_id, '_gb_toa_signed_date', true);
        $a_ip      = get_post_meta($agr_id, '_gb_toa_signed_ip', true);
        $a_version = get_post_meta($agr_id, '_gb_toa_signed_version', true);
        $a_text    = get_post_meta($agr_id, '_gb_toa_signed_text', true);
        if (trim((string) $a_text) === '' && function_exists('gb_get_terms_of_association')) {
            $a_text = gb_get_terms_of_association();
        }
        ?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Terms of Association — <?php echo esc_html($a_name); ?></title>
        <style>
            body { font-family: Georgia, 'Times New Roman', serif; max-width: 800px; margin: 40px auto; padding: 0 30px; color: #1a1a1a; line-height: 1.6; }
            h1 { font-size: 22px; border-bottom: 2px solid #1b5e20; padding-bottom: 10px; }
            .doc-body { white-space: pre-wrap; font-size: 14px; margin: 24px 0; }
            .sig { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px; font-size: 14px; }
            .sig .name { font-size: 22px; font-family: 'Segoe Script', 'Brush Script MT', cursive; color: #1b5e20; }
            .meta { color: #555; font-size: 12px; margin-top: 12px; }
            .noprint { margin: 20px 0; }
            @media print { .noprint { display: none; } body { margin: 0; } }
            button { background: #1b5e20; color: #fff; border: 0; padding: 10px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; }
        </style></head><body>
        <div class="noprint"><button onclick="window.print()">🖨 Print / Save as PDF</button></div>
        <h1><?php echo esc_html(get_bloginfo('name')); ?> — Terms of Association</h1>
        <div class="doc-body"><?php echo wp_kses_post($a_text); ?></div>
        <div class="sig">
            <div>Signed by:</div>
            <div class="name"><?php echo esc_html($a_name); ?></div>
            <div><strong><?php echo esc_html($a_title); ?></strong></div>
            <div class="meta">
                Cleaner profile: <?php echo esc_html(get_the_title($agr_id)); ?><br>
                Date signed: <?php echo $a_date ? esc_html(date_i18n('j F Y, g:i a', strtotime($a_date))) : '—'; ?><br>
                Agreement version: <?php echo esc_html($a_version ?: 'n/a'); ?><?php echo $a_ip ? ' · IP: ' . esc_html($a_ip) : ''; ?>
            </div>
        </div>
        </body></html><?php
        exit;
    }
    wp_die('No signed agreement found for this cleaner.');
}

// In-dashboard message replies are handled by the shared init handler in
// functions.php so the manager inbox and cleaner inbox use the same flow.

// 2. CONTROLLER: HANDLE SAVES & ACTIONS
if (isset($_POST['gb_manager_action']) && sanitize_key(wp_unslash($_POST['gb_manager_action'])) === 'create_blog_post') {
    if (empty($_POST['gb_manager_nonce']) || !wp_verify_nonce($_POST['gb_manager_nonce'], 'gb_manager_action')) {
        wp_safe_redirect(home_url('/manager-dashboard/?tab=blog&msg=security_refresh'));
        exit;
    }

    $pid = wp_insert_post([
        'post_type'    => 'post',
        'post_title'   => 'Untitled Draft',
        'post_content' => '',
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
    ], true);

    if (is_wp_error($pid) || !$pid) {
        wp_safe_redirect(home_url('/manager-dashboard/?tab=blog&msg=create_failed'));
        exit;
    }

    wp_safe_redirect(home_url('/manager-dashboard/?tab=blog&view=edit&id=' . (int) $pid . '&msg=draft_created'));
    exit;
}

if (isset($_POST['gb_save_action']) && isset($_POST['post_id'])) {
    if (empty($_POST['gb_manager_nonce']) || !wp_verify_nonce($_POST['gb_manager_nonce'], 'gb_manager_action')) {
        $redirect_args = [
            'tab'    => isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview',
            'view'   => isset($_GET['view']) ? sanitize_key($_GET['view']) : '',
            'id'     => isset($_POST['post_id']) ? intval($_POST['post_id']) : 0,
            'msg'    => 'security_refresh',
        ];
        wp_safe_redirect(add_query_arg(array_filter($redirect_args, static function ($value) {
            return $value !== '' && $value !== 0;
        }), home_url('/manager-dashboard/')));
        exit;
    }

    $pid = intval($_POST['post_id']);
    $save_action = sanitize_key(wp_unslash($_POST['gb_save_action']));

    switch ($save_action) {
        case 'save_page':
            if (!$pid || get_post_type($pid) !== 'page') {
                wp_die('Invalid page target.');
            }
            break;
        case 'save_post':
            if ($pid && get_post_type($pid) !== 'post') {
                wp_die('Invalid post target.');
            }
            break;
        case 'save_cleaner':
            if ($pid && get_post_type($pid) !== 'gb_cleaner') {
                wp_die('Invalid cleaner target.');
            }
            break;
        case 'save_booking':
            if (!$pid || get_post_type($pid) !== 'gb_booking') {
                wp_die('Invalid booking target.');
            }
            break;
        case 'save_service':
            if ($pid && get_post_type($pid) !== 'gb_service') {
                wp_die('Invalid service target.');
            }
            break;
        case 'save_eco_product':
            if ($pid && get_post_type($pid) !== 'gb_eco_product') {
                wp_die('Invalid eco product target.');
            }
            break;
        case 'save_settings':
            if ($pid !== (int) get_option('page_on_front')) {
                wp_die('Invalid settings target.');
            }
            break;
        default:
            wp_die('Unsupported save action.');
    }
    
    // Global Meta Saver
    foreach ($_POST as $key => $value) {
        if (strpos($key, '_gb_') === 0) {
            // Secret fields are intentionally blank when a saved key is being
            // kept. These are write-only in the UI: the settings screen no
            // longer renders the stored value into the page source, so a blank
            // submission means "unchanged", never "clear it".
            $gb_write_only_secrets = [
                '_gb_brevo_api_key',
                '_gb_stripe_test_secret_key',
                '_gb_stripe_test_webhook_secret',
                '_gb_stripe_test_connect_webhook_secret',
                '_gb_stripe_live_secret_key',
                '_gb_stripe_live_webhook_secret',
                '_gb_stripe_live_connect_webhook_secret',
                '_gb_stripe_secret_key',
                '_gb_stripe_webhook_secret',
                '_gb_stripe_connect_webhook_secret',
            ];
            if (in_array($key, $gb_write_only_secrets, true) && trim((string) wp_unslash($value)) === '') {
                continue;
            }
            update_post_meta($pid, $key, gb_manager_sanitize_meta_value($value));
        }
    }

    // A. SAVE PAGES
    if ($save_action === 'save_page') {
        if (isset($_POST['gb_main_content'])) {
            wp_update_post(['ID' => $pid, 'post_content' => wp_kses_post(wp_unslash($_POST['gb_main_content']))]);
        }
        wp_redirect(home_url('/manager-dashboard?tab=pages&action=edit&id='.$pid.'&msg=saved')); exit;
    }
    
    // B. SAVE BLOG POSTS
    if ($save_action === 'save_post') {
        $allowed_statuses = ['publish', 'draft', 'pending', 'private'];
        $post_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : 'draft';
        if (!in_array($post_status, $allowed_statuses, true)) {
            $post_status = 'draft';
        }

        $post_data = [
            'ID' => $pid,
            'post_title' => isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '',
            'post_content' => isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '',
            'post_status' => $post_status
        ];
        
        if ($pid === 0) { 
            unset($post_data['ID']); 
            $post_data['post_type'] = 'post';
            $post_data['post_author'] = get_current_user_id();
            $pid = wp_insert_post($post_data); 
        } else { 
            wp_update_post($post_data); 
        }

        // Native WP Featured Image Saving
        if (isset($_POST['_thumbnail_id'])) {
            set_post_thumbnail($pid, intval($_POST['_thumbnail_id']));
        }
        
        // Sync SEO Score
        if (isset($_POST['_gb_seo_score'])) {
            update_post_meta($pid, '_gb_seo_score', intval($_POST['_gb_seo_score']));
        }
        
        wp_safe_redirect(home_url('/manager-dashboard/?tab=blog&view=edit&id='.$pid.'&msg=saved')); exit;
    }

    // C. SAVE CLEANERS
    if ($save_action === 'save_cleaner') {
        $allowed_cleaner_statuses = ['publish', 'pending', 'draft'];
        $cleaner_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : 'draft';
        if (!in_array($cleaner_status, $allowed_cleaner_statuses, true)) {
            $cleaner_status = 'draft';
        }

        $hourly_rate = isset($_POST['hourly_rate']) ? sanitize_text_field(wp_unslash($_POST['hourly_rate'])) : '';
        $location = isset($_POST['_gb_location']) ? sanitize_text_field(wp_unslash($_POST['_gb_location'])) : '';
        $payout_interval = isset($_POST['_gb_stripe_payout_interval']) ? gb_normalize_payout_interval(wp_unslash($_POST['_gb_stripe_payout_interval'])) : 'daily';

        $post_data = [
            'ID' => $pid,
            'post_title' => isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '',
            'post_content' => isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '',
            'post_status' => $cleaner_status,
            'post_type' => 'gb_cleaner'
        ];
        if ($pid === 0) { unset($post_data['ID']); $pid = wp_insert_post($post_data); } 
        else { wp_update_post($post_data); }
        
        if (isset($_POST['_thumbnail_id'])) {
            set_post_thumbnail($pid, intval($_POST['_thumbnail_id']));
        }
        update_post_meta($pid, 'hourly_rate', $hourly_rate);

        // Keep location keys synced for manager/public cleaner pages.
        update_post_meta($pid, '_gb_location', $location);
        update_post_meta($pid, 'location', $location);
        update_post_meta($pid, 'cleaner_location', $location);

        // Keep verification badge state aligned with manager status selection.
        gb_sync_cleaner_verification_state($pid, $cleaner_status);
        if ($cleaner_status === 'publish') {
            gb_purge_cleaner_verification_documents($pid);
        }
        update_post_meta($pid, '_gb_stripe_payout_interval', $payout_interval);

        $connected_account_id = sanitize_text_field((string) get_post_meta($pid, '_gb_stripe_account_id', true));
        if ($connected_account_id !== '') {
            $schedule_sync = gb_update_connected_account_payout_schedule($connected_account_id, $payout_interval);
            if (is_wp_error($schedule_sync)) {
                update_post_meta($pid, '_gb_stripe_last_error', $schedule_sync->get_error_message());
            } else {
                gb_sync_cleaner_connect_account($pid);
            }
        }
        
        wp_redirect(home_url('/manager-dashboard?tab=cleaners&action=edit&id='.$pid.'&msg=saved')); exit;
    }

    // D. SAVE BOOKINGS
    if ($save_action === 'save_booking') {
        $allowed_booking_statuses = ['publish', 'pending', 'trash'];
        $booking_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : 'publish';
        if (!in_array($booking_status, $allowed_booking_statuses, true)) {
            $booking_status = 'publish';
        }

        $customer_name = isset($_POST['_gb_customer_name']) ? sanitize_text_field(wp_unslash($_POST['_gb_customer_name'])) : '';
        $booking_date = isset($_POST['_gb_booking_date']) ? sanitize_text_field(wp_unslash($_POST['_gb_booking_date'])) : '';
        $booking_time = isset($_POST['_gb_booking_time']) ? sanitize_text_field(wp_unslash($_POST['_gb_booking_time'])) : '';
        $new_title = sprintf('%s - %s (%s)', $customer_name, $booking_date, $booking_time);

        wp_update_post(['ID' => $pid, 'post_title' => $new_title, 'post_status' => $booking_status]);

        // Keep lifecycle meta aligned with manager-selected post status.
        if (!isset($_POST['_gb_booking_status'])) {
            if ($booking_status === 'publish') {
                update_post_meta($pid, '_gb_booking_status', 'confirmed');
            } elseif ($booking_status === 'pending') {
                update_post_meta($pid, '_gb_booking_status', 'pending_payment');
            } elseif ($booking_status === 'trash') {
                update_post_meta($pid, '_gb_booking_status', 'cancelled');
            }
        }

        wp_redirect(home_url('/manager-dashboard?tab=bookings&action=edit&id='.$pid.'&msg=saved')); exit;
    }

    // E. SAVE SERVICES
    if ($save_action === 'save_service') {
        $duration = isset($_POST['_gb_service_duration']) ? intval(wp_unslash($_POST['_gb_service_duration'])) : 2;
        if ($duration < 1) {
            $duration = 1;
        }

        $icon = isset($_POST['_gb_service_icon']) ? sanitize_text_field(wp_unslash($_POST['_gb_service_icon'])) : '🧹';
        $service_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : 'publish';
        if (!in_array($service_status, ['publish', 'draft'], true)) {
            $service_status = 'publish';
        }
        $service_price_raw = isset($_POST['service_price']) ? wp_unslash($_POST['service_price']) : '0';
        $service_price = is_numeric($service_price_raw) ? (float) $service_price_raw : 0.0;
        if ($service_price < 0) {
            $service_price = 0.0;
        }
        $service_destination_cleaner_id = isset($_POST['_gb_service_destination_cleaner_id']) ? absint($_POST['_gb_service_destination_cleaner_id']) : 0;

        $post_data = [
            'ID' => $pid,
            'post_title' => isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '',
            'post_content' => isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '',
            'post_status' => $service_status,
            'post_type' => 'gb_service'
        ];
        if ($pid === 0) { unset($post_data['ID']); $pid = wp_insert_post($post_data); } 
        else { wp_update_post($post_data); }

        update_post_meta($pid, '_gb_service_duration', $duration);
        update_post_meta($pid, '_gb_service_icon', $icon);
        update_post_meta($pid, 'service_price', number_format($service_price, 2, '.', ''));
        update_post_meta($pid, '_gb_service_destination_cleaner_id', $service_destination_cleaner_id);

        $stripe_sync_state = 'not_configured';
        $stripe_sync_message = '';
        if (function_exists('gb_sync_service_to_stripe_product')) {
            $sync_result = gb_sync_service_to_stripe_product($pid);
            if (is_wp_error($sync_result)) {
                $stripe_sync_state = 'error';
                $stripe_sync_message = $sync_result->get_error_message();
            } else {
                $stripe_sync_state = 'ok';
            }
        }

        wp_redirect(add_query_arg([
            'tab' => 'services',
            'action' => 'edit',
            'id' => $pid,
            'msg' => 'saved',
            'stripe_sync' => $stripe_sync_state,
            'stripe_sync_msg' => $stripe_sync_message,
        ], home_url('/manager-dashboard'))); exit;
    }

    // E2. SAVE ECO PRODUCTS
    if ($save_action === 'save_eco_product') {
        $eco_status = isset($_POST['post_status']) ? sanitize_key(wp_unslash($_POST['post_status'])) : 'publish';
        if (!in_array($eco_status, ['publish', 'draft'], true)) {
            $eco_status = 'publish';
        }

        $post_data = [
            'ID'           => $pid,
            'post_title'   => isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '',
            'post_content' => isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '',
            'post_excerpt' => isset($_POST['post_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['post_excerpt'])) : '',
            'post_status'  => $eco_status,
            'post_type'    => 'gb_eco_product',
            'menu_order'   => isset($_POST['menu_order']) ? intval(wp_unslash($_POST['menu_order'])) : 0,
        ];
        if ($pid === 0) {
            unset($post_data['ID']);
            $pid = wp_insert_post($post_data);
        } else {
            wp_update_post($post_data);
        }

        if (!$pid || is_wp_error($pid)) {
            wp_redirect(home_url('/manager-dashboard?tab=eco-products&msg=save_failed')); exit;
        }

        // On a brand new product the global _gb_ meta saver above ran against
        // post 0, so these fields have to be written once the post exists.
        $eco_file = isset($_POST['_gb_eco_file']) ? absint($_POST['_gb_eco_file']) : 0;
        if ($eco_file && get_post_type($eco_file) === 'attachment') {
            update_post_meta($pid, '_gb_eco_file', $eco_file);
        } else {
            delete_post_meta($pid, '_gb_eco_file');
        }

        // Site-relative paths ("/make-your-own") are as valid here as absolute
        // URLs, and esc_url_raw() would keep them, so only trim and strip tags.
        $eco_link = isset($_POST['_gb_eco_link']) ? trim(sanitize_text_field(wp_unslash($_POST['_gb_eco_link']))) : '';
        if ($eco_link !== '') {
            update_post_meta($pid, '_gb_eco_link', $eco_link);
        } else {
            delete_post_meta($pid, '_gb_eco_link');
        }

        // Same reason as the fields above: on a new product the global _gb_
        // meta saver ran against post 0, so these are written here.
        foreach (['_gb_eco_cta_label', '_gb_eco_soon_label'] as $eco_label_key) {
            $eco_label = isset($_POST[$eco_label_key]) ? trim(sanitize_text_field(wp_unslash($_POST[$eco_label_key]))) : '';
            if ($eco_label !== '') {
                update_post_meta($pid, $eco_label_key, $eco_label);
            } else {
                delete_post_meta($pid, $eco_label_key);
            }
        }

        $eco_thumb = isset($_POST['gb_eco_thumb_id']) ? absint($_POST['gb_eco_thumb_id']) : 0;
        if ($eco_thumb && get_post_type($eco_thumb) === 'attachment') {
            set_post_thumbnail($pid, $eco_thumb);
        } else {
            delete_post_thumbnail($pid);
        }

        wp_redirect(home_url('/manager-dashboard?tab=eco-products&msg=saved')); exit;
    }

    if ($save_action === 'save_settings') {
        $settings_section = isset($_POST['_gb_settings_section']) ? sanitize_key(wp_unslash($_POST['_gb_settings_section'])) : 'env';
        $allowed_settings_sections = ['env', 'safeguarding', 'email', 'social', 'agreement', 'monetisation', 'stripe', 'stripe-tools', 'account'];
        if (!in_array($settings_section, $allowed_settings_sections, true)) {
            $settings_section = 'env';
        }
        wp_safe_redirect(add_query_arg([
            'tab' => 'settings',
            'section' => $settings_section,
            'msg' => 'saved',
        ], home_url('/manager-dashboard'))); exit;
    }
}

// 3. HANDLE ACTIONS
if (isset($_GET['action']) && isset($_GET['id'])) {
    $mid = intval($_GET['id']);
    if ($action === 'delete_message' || $action === 'mark_replied' || $action === 'toggle_service' || $action === 'sync_service_product' || $action === 'delete_entity' || $action === 'archive_message' || $action === 'unarchive_message') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gb_manager_action_' . $action . '_' . $mid)) {
            wp_die('Security check failed.');
        }
    }

    if ($action === 'delete_message') {
        if (get_post_type($mid) == 'gb_message') { wp_trash_post($mid); wp_redirect(home_url('/manager-dashboard?tab=support&msg=deleted')); exit; }
    }
    if ($action === 'mark_replied') {
        if (get_post_type($mid) == 'gb_message') { update_post_meta($mid, '_gb_responded_date', current_time('mysql')); wp_redirect(home_url('/manager-dashboard?tab=support&action=view_message&id='.$mid.'&msg=replied')); exit; }
    }
    if ($action === 'archive_message') {
        if (get_post_type($mid) == 'gb_message') { update_post_meta($mid, '_gb_archived', '1'); wp_redirect(home_url('/manager-dashboard?tab=support&msg=archived')); exit; }
    }
    if ($action === 'unarchive_message') {
        if (get_post_type($mid) == 'gb_message') { delete_post_meta($mid, '_gb_archived'); wp_redirect(home_url('/manager-dashboard?tab=support&view=archived&msg=unarchived')); exit; }
    }

    if ($action === 'toggle_service') {
        if (get_post_type($mid) === 'gb_service') {
            $target_state = isset($_GET['state']) ? sanitize_key(wp_unslash($_GET['state'])) : 'draft';
            if (!in_array($target_state, ['publish', 'draft'], true)) {
                $target_state = 'draft';
            }

            wp_update_post([
                'ID' => $mid,
                'post_status' => $target_state,
            ]);

            $sync_state = 'ok';
            $sync_msg = '';
            if (function_exists('gb_sync_service_to_stripe_product')) {
                $sync = gb_sync_service_to_stripe_product($mid, true);
                if (is_wp_error($sync)) {
                    $sync_state = 'error';
                    $sync_msg = $sync->get_error_message();
                }
            }

            wp_redirect(add_query_arg([
                'tab' => 'services',
                'msg' => 'saved',
                'stripe_sync' => $sync_state,
                'stripe_sync_msg' => $sync_msg,
            ], home_url('/manager-dashboard'))); exit;
        }
    }

    if ($action === 'sync_service_product') {
        if (get_post_type($mid) === 'gb_service') {
            $sync_state = 'ok';
            $sync_msg = '';
            if (function_exists('gb_sync_service_to_stripe_product')) {
                $sync = gb_sync_service_to_stripe_product($mid, true);
                if (is_wp_error($sync)) {
                    $sync_state = 'error';
                    $sync_msg = $sync->get_error_message();
                }
            }

            wp_redirect(add_query_arg([
                'tab' => 'services',
                'msg' => 'saved',
                'stripe_sync' => $sync_state,
                'stripe_sync_msg' => $sync_msg,
            ], home_url('/manager-dashboard'))); exit;
        }
    }

    // Unified delete: eco products, services, bookings, cleaners, blog posts, pages.
    if ($action === 'delete_entity') {
        $type_to_tab = [
            'gb_service'     => 'services',
            'gb_eco_product' => 'eco-products',
            'gb_booking'     => 'bookings',
            'gb_cleaner'     => 'cleaners',
            'post'           => 'blog',
            'page'           => 'pages',
        ];
        $ptype = get_post_type($mid);
        if ($mid && isset($type_to_tab[$ptype])) {
            // Bookings represent a "cancelled" appointment using the 'trash'
            // post status, and the bookings list intentionally shows trashed
            // (cancelled) rows. That means wp_trash_post() is a no-op on an
            // already-cancelled booking and never removes it — so "Delete"
            // appeared to do nothing for confirmed, pending OR cancelled
            // bookings. Permanently delete bookings so they actually disappear.
            if ($ptype === 'gb_booking') {
                wp_delete_post($mid, true);
            } else {
                wp_trash_post($mid);
            }
            wp_redirect(home_url('/manager-dashboard?tab=' . $type_to_tab[$ptype] . '&msg=deleted')); exit;
        }
        wp_redirect(home_url('/manager-dashboard?msg=delete_failed')); exit;
    }
}

// 4. APPROVE CLEANER
if (isset($_GET['approve_cleaner']) && (in_array('gb_manager', (array) $current_user->roles, true) || current_user_can('administrator'))) {
    $cleaner_id = intval($_GET['approve_cleaner']);
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gb_manager_action_approve_cleaner_' . $cleaner_id)) {
        wp_die('Security check failed.');
    }

    $cleaner_id = intval($_GET['approve_cleaner']);
    if (get_post_type($cleaner_id) === 'gb_cleaner') {
        // Hard block: cannot go live until DBS + ID + address docs, phone and
        // two references are all present.
        $missing = function_exists('gb_cleaner_verification_missing') ? gb_cleaner_verification_missing($cleaner_id) : array();
        if (!empty($missing)) {
            wp_redirect(home_url('/manager-dashboard?tab=cleaners&action=edit&id=' . $cleaner_id . '&msg=incomplete')); exit;
        }
        wp_update_post(array('ID' => $cleaner_id, 'post_status' => 'publish'));
        gb_sync_cleaner_verification_state($cleaner_id, 'publish');
        gb_purge_cleaner_verification_documents($cleaner_id);
        wp_redirect(home_url('/manager-dashboard?tab=cleaners&msg=approved')); exit;
    }
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager OS | <?php bloginfo('name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
    
    <style>
        html { margin-top: 0 !important; }
        /* Buttons in this dashboard set display:inline-flex, which beats the
           [hidden] attribute's default display:none — so "Remove" buttons kept
           showing on rows with nothing to remove, in Settings > Documents as
           well as Eco Products. One guard covers every tab. */
        [hidden] { display: none !important; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f8fafc; color: #334155; padding: 0; overflow-x: hidden; }
        .gb-app { display: flex; min-height: 100vh; width: 100%; }
        .gb-sidebar { width: 260px; background: #0f172a; padding: 30px 0; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; top:0; left:0; border-right: 1px solid rgba(255,255,255,0.05); }
        .gb-main { margin-left: 260px; flex-grow: 1; padding: 40px 5%; width: calc(100% - 260px); box-sizing: border-box; }
        .gb-brand { padding: 0 30px 40px; display: flex; align-items: center; }
        .gb-logo-wrapper { background: #ffffff; padding: 12px 15px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; justify-content: center; align-items: center; width: 100%; box-sizing: border-box; }
        .gb-logo-img { max-width: 100%; height: auto; display: block; }
        .gb-nav-item { display: flex; align-items: center; gap: 14px; padding: 14px 30px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; border-left: 3px solid transparent; }
        .gb-nav-item:hover { color: white; background: rgba(255,255,255,0.03); }
        .gb-nav-item.active { color: #10b981; background: rgba(16, 185, 129, 0.1); border-left-color: #10b981; }
        .gb-nav-item .dashicons { font-size: 18px; width: 18px; height: 18px; }
        .gb-title { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.5px; }
        .gb-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; }
        /* MOBILE
           The sidebar used to be display:none below 1024px, which left the
           dashboard with no navigation at all on a phone — every tab was
           unreachable once you were in one. It is now a slide-in drawer
           driven by a top bar, reusing the same nav markup. */
        .gb-mobile-bar { display: none; }
        .gb-sidebar-backdrop { display: none; }

        @media (max-width: 1024px) {
            .gb-main { margin-left: 0; width: 100%; padding: 20px; padding-top: 84px; }

            .gb-mobile-bar {
                display: flex; align-items: center; gap: 14px;
                position: fixed; top: 0; left: 0; right: 0; z-index: 300;
                height: 64px; padding: 0 16px; box-sizing: border-box;
                background: #0f172a; border-bottom: 1px solid rgba(255,255,255,.08);
            }
            .gb-mobile-bar img { height: 30px; width: auto; display: block; background: #fff; padding: 5px 8px; border-radius: 7px; }
            .gb-mobile-title { color: #e2e8f0; font-size: 14px; font-weight: 700; margin-left: auto; text-transform: capitalize; }
            .gb-menu-btn {
                display: inline-flex; align-items: center; justify-content: center;
                width: 42px; height: 42px; flex: 0 0 42px; padding: 0;
                background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16);
                border-radius: 9px; color: #fff; cursor: pointer;
            }
            .gb-menu-btn .dashicons { font-size: 22px; width: 22px; height: 22px; }

            .gb-sidebar {
                display: flex; width: 280px; max-width: 86vw; z-index: 400;
                transform: translateX(-100%); transition: transform .25s ease;
                overflow-y: auto; padding-top: 20px;
            }
            .gb-sidebar.is-open { transform: translateX(0); }
            .gb-sidebar-backdrop {
                display: block; position: fixed; inset: 0; z-index: 350;
                background: rgba(15,23,42,.55); opacity: 0; pointer-events: none;
                transition: opacity .25s ease;
            }
            .gb-sidebar-backdrop.is-open { opacity: 1; pointer-events: auto; }
            .gb-nav-item { padding: 15px 24px; }

            /* Stop any single wide child forcing the whole page sideways. */
            .gb-app, .gb-main { max-width: 100vw; overflow-x: hidden; }
        }

        @media (max-width: 760px) {
            .gb-main { padding: 16px; padding-top: 80px; }

            /* Page headers: title and action button stack instead of squashing
               the button into two words per line. */
            .gb-main .gb-edit-header,
            .gb-main .gb-services-header,
            .gb-main .gb-support-header,
            .gb-main .gb-builder-header,
            .gb-main .booking-controls,
            .gb-main .gb-header,
            .gb-main .gb-eco-toolbar,
            .gb-main .gb-services-toolbar {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 14px !important;
            }
            .gb-main .gb-edit-header > *, .gb-main .gb-eco-toolbar > * { min-width: 0; }
            .gb-main .gb-edit-header button,
            .gb-main .gb-edit-header .gb-header-btn,
            .gb-main .gb-eco-primary,
            .gb-main .gb-btn-primary-os {
                width: 100%; justify-content: center; white-space: nowrap;
            }
            .gb-main .gb-title { font-size: 22px !important; }

            /* Two-column form rows are laid out with inline grid styles all over
               these tabs; collapse them rather than editing every template. */
            .gb-main [style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
            .gb-main .gb-link-row { display: block !important; }

            /* The rule above only reaches INLINE grid styles. These layouts are
               defined in each tab's stylesheet with a fixed second column
               (1fr 320px, 280px 1fr, and so on), so they never collapsed and
               forced the panel sideways - which is how the page builder's
               image field ended up overlapping its own buttons. */
            .gb-main .gb-blog-layout,
            .gb-main .gb-builder-layout,
            .gb-main .gb-os-grid,
            .gb-main .gb-settings-shell,
            .gb-main .gb-support-grid,
            .gb-main .gb-grid-overview,
            .gb-main .gb-quick-action-grid,
            .gb-main .gb-media-field {
                grid-template-columns: 1fr !important;
            }

            /* Media picker: stack the field, let its buttons wrap instead of
               being squeezed, and cap the preview so it cannot dominate. */
            .gb-main .gb-media-toolbar { flex-wrap: wrap !important; }
            .gb-main .gb-media-input-row { flex: 1 1 100% !important; }
            .gb-main .gb-media-actions { width: 100%; }
            .gb-main .gb-media-actions .gb-btn-small-os { flex: 1 1 0; justify-content: center; }
            .gb-main .gb-media-preview { max-height: 180px; }
            .gb-main .gb-media-preview.is-empty { min-height: 0; height: 88px; }
            .gb-main .gb-media-preview img { max-height: 180px; object-fit: contain; }

            /* Tables scroll via .gb-table-scroll rather than display:block on
               the table itself, which detaches thead from tbody and misaligns
               every column. */

            .gb-card, .gb-config-card, .gb-editor-section { padding: 16px !important; }

            /* iOS zooms the page in on focus for any input under 16px. */
            .gb-main input, .gb-main select, .gb-main textarea { font-size: 16px !important; }

            .gb-eco-cards { grid-template-columns: 1fr !important; }
            .gb-settings-shell, .gb-settings-layout { display: block !important; }
        }
    </style>
</head>
<body>

<div class="gb-app">
    <header class="gb-mobile-bar">
        <button type="button" class="gb-menu-btn" id="gb-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="gb-sidebar">
            <span class="dashicons dashicons-menu" aria-hidden="true"></span>
        </button>
        <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png" alt="Green Broom">
        <span class="gb-mobile-title"><?php echo esc_html(str_replace('-', ' ', $tab === 'overview' ? 'Dashboard' : $tab)); ?></span>
    </header>
    <div class="gb-sidebar-backdrop" id="gb-sidebar-backdrop" hidden></div>

    <nav class="gb-sidebar" id="gb-sidebar">
        <div class="gb-brand">
            <div class="gb-logo-wrapper">
                <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-main-logo.png" alt="Green Broom" class="gb-logo-img">
            </div>
        </div>
        
        <a href="?tab=overview" class="gb-nav-item <?php echo $tab=='overview'||$tab=='dashboard'?'active':''; ?>"><span class="dashicons dashicons-dashboard"></span> Dashboard</a>
        <a href="?tab=bookings" class="gb-nav-item <?php echo $tab=='bookings'?'active':''; ?>"><span class="dashicons dashicons-calendar-alt"></span> Bookings</a>
        <a href="?tab=cleaners" class="gb-nav-item <?php echo $tab=='cleaners'?'active':''; ?>"><span class="dashicons dashicons-id"></span> Manage Cleaners</a>
        <a href="?tab=pages" class="gb-nav-item <?php echo $tab=='pages'?'active':''; ?>"><span class="dashicons dashicons-welcome-write-blog"></span> Content</a>
        <a href="?tab=eco-products" class="gb-nav-item <?php echo $tab=='eco-products'?'active':''; ?>"><span class="dashicons dashicons-carrot"></span> Eco Products</a>
        <a href="?tab=blog" class="gb-nav-item <?php echo $tab=='blog'?'active':''; ?>"><span class="dashicons dashicons-edit"></span> Blog & SEO</a>
        <a href="?tab=support" class="gb-nav-item <?php echo $tab=='support'?'active':''; ?>"><span class="dashicons dashicons-email"></span> Inbox</a>
        <a href="?tab=helpdesk" class="gb-nav-item <?php echo $tab=='helpdesk'?'active':''; ?>">
            <span class="dashicons dashicons-editor-help"></span> Helpdesk
        </a>
        <a href="?tab=services" class="gb-nav-item <?php echo $tab=='services'?'active':''; ?>"><span class="dashicons dashicons-list-view"></span> Services</a>
        <a href="?tab=settings" class="gb-nav-item <?php echo $tab=='settings'?'active':''; ?>">
            <span class="dashicons dashicons-admin-generic"></span> Settings
        </a>
        <div style="flex-grow:1;"></div>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="gb-nav-item"><span class="dashicons dashicons-migrate"></span> Log Out</a>
    </nav>

    <main class="gb-main gb-tab-<?php echo esc_attr($tab); ?>">
        <?php if($msg == 'saved'): ?><div class="gb-card" style="margin-bottom: 20px; background:#f0fdf4; border-color:#dcfce7; color:#166534; padding:15px;">✅ Saved successfully.</div><?php endif; ?>
        <?php if($msg == 'approved'): ?><div class="gb-card" style="margin-bottom: 20px; background:#f0fdf4; border-color:#dcfce7; color:#166534; padding:15px;">✅ Cleaner approved.</div><?php endif; ?>
        <?php if($msg == 'deleted'): ?><div class="gb-card" style="margin-bottom: 20px; background:#fef2f2; border-color:#fee2e2; color:#991b1b; padding:15px;">🗑️ Item deleted.</div><?php endif; ?>
        <?php if($msg == 'save_failed'): ?><div class="gb-card" style="margin-bottom: 20px; background:#fef2f2; border-color:#fee2e2; color:#991b1b; padding:15px;">⚠️ That could not be saved. Please check the title is filled in and try again.</div><?php endif; ?>

        <?php 
        $file = get_template_directory() . '/template-parts/dashboard/tab-' . $tab . '.php';
        if($tab == 'dashboard') $file = get_template_directory() . '/template-parts/dashboard/tab-overview.php';
        
        if (file_exists($file)) { 
            include $file; 
        } else { 
            echo '<div class="gb-card">Module not found: '.esc_html($tab).'</div>';
        }
        ?>

        <style id="gb-manager-unified-ui">
            /* Unified visual language for all manager tabs except Dashboard/Overview. */
            .gb-main:not(.gb-tab-overview) .gb-edit-header,
            .gb-main:not(.gb-tab-overview) .gb-services-header,
            .gb-main:not(.gb-tab-overview) .gb-support-header,
            .gb-main:not(.gb-tab-overview) .gb-builder-header,
            .gb-main:not(.gb-tab-overview) .booking-controls,
            .gb-main:not(.gb-tab-overview) .gb-header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
                gap: 16px !important;
                margin-bottom: 24px !important;
                background: transparent !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-title,
            .gb-main:not(.gb-tab-overview) .gb-edit-header h1,
            .gb-main:not(.gb-tab-overview) .gb-services-header h1,
            .gb-main:not(.gb-tab-overview) .gb-support-header h1,
            .gb-main:not(.gb-tab-overview) .gb-builder-header h1,
            .gb-main:not(.gb-tab-overview) .booking-controls h1,
            .gb-main:not(.gb-tab-overview) .gb-header h1 {
                margin: 0 !important;
                font-size: 28px !important;
                font-weight: 800 !important;
                color: #0f172a !important;
                letter-spacing: -0.6px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-edit-header p,
            .gb-main:not(.gb-tab-overview) .gb-services-header p,
            .gb-main:not(.gb-tab-overview) .gb-support-header p,
            .gb-main:not(.gb-tab-overview) .gb-builder-header p,
            .gb-main:not(.gb-tab-overview) .booking-controls p,
            .gb-main:not(.gb-tab-overview) .gb-header p {
                margin: 6px 0 0 !important;
                color: #64748b !important;
                font-size: 14px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-back-link {
                text-decoration: none !important;
                color: #2e7d32 !important;
                font-weight: 700 !important;
                font-size: 13px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-btn-primary-os,
            .gb-main:not(.gb-tab-overview) .gb-header-btn,
            .gb-main:not(.gb-tab-overview) .gb-btn-small-os,
            .gb-main:not(.gb-tab-overview) .btn-os {
                border-radius: 8px !important;
                font-weight: 700 !important;
                text-decoration: none !important;
                transition: all 0.2s ease !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-btn-primary-os,
            .gb-main:not(.gb-tab-overview) .gb-header-btn {
                background: #2e7d32 !important;
                color: #ffffff !important;
                border: 1px solid #2e7d32 !important;
                height: 44px !important;
                padding: 0 20px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 13px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-btn-primary-os:hover,
            .gb-main:not(.gb-tab-overview) .gb-header-btn:hover {
                background: #1b5e20 !important;
                border-color: #1b5e20 !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-btn-small-os,
            .gb-main:not(.gb-tab-overview) .btn-os {
                background: #ffffff !important;
                color: #334155 !important;
                border: 1px solid #e2e8f0 !important;
                padding: 9px 14px !important;
                font-size: 12px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-btn-small-os:hover,
            .gb-main:not(.gb-tab-overview) .btn-os:hover {
                background: #f8fafc !important;
                border-color: #cbd5e1 !important;
            }

            .gb-main:not(.gb-tab-overview) .btn-os.active {
                background: #2e7d32 !important;
                color: #ffffff !important;
                border-color: #2e7d32 !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-card,
            .gb-main:not(.gb-tab-overview) .gb-os-card,
            .gb-main:not(.gb-tab-overview) .gb-panel,
            .gb-main:not(.gb-tab-overview) .gb-config-card,
            .gb-main:not(.gb-tab-overview) .gb-editor-section,
            .gb-main:not(.gb-tab-overview) .gb-nav-card,
            .gb-main:not(.gb-tab-overview) .gb-writer-card,
            .gb-main:not(.gb-tab-overview) .gb-log-box {
                border: 1px solid #e2e8f0 !important;
                border-radius: 12px !important;
                background: #ffffff !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            }

            .gb-main:not(.gb-tab-overview) table.gb-booking-table,
            .gb-main:not(.gb-tab-overview) table.gb-table,
            .gb-main:not(.gb-tab-overview) table.gb-table-os,
            .gb-main:not(.gb-tab-overview) table.gb-blog-table,
            .gb-main:not(.gb-tab-overview) table.gb-services-table,
            .gb-main:not(.gb-tab-overview) table.gb-support-table,
            .gb-main:not(.gb-tab-overview) table.gb-helpdesk-table {
                width: 100% !important;
                border-collapse: collapse !important;
                background: #ffffff !important;
            }

            .gb-main:not(.gb-tab-overview) table.gb-booking-table th,
            .gb-main:not(.gb-tab-overview) table.gb-table th,
            .gb-main:not(.gb-tab-overview) table.gb-table-os th,
            .gb-main:not(.gb-tab-overview) table.gb-blog-table th,
            .gb-main:not(.gb-tab-overview) table.gb-services-table th,
            .gb-main:not(.gb-tab-overview) table.gb-support-table th,
            .gb-main:not(.gb-tab-overview) table.gb-helpdesk-table th {
                padding: 14px 15px !important;
                background: #f8fafc !important;
                color: #64748b !important;
                font-size: 10px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                border-bottom: 1px solid #e2e8f0 !important;
                text-align: left !important;
            }

            .gb-main:not(.gb-tab-overview) table.gb-booking-table td,
            .gb-main:not(.gb-tab-overview) table.gb-table td,
            .gb-main:not(.gb-tab-overview) table.gb-table-os td,
            .gb-main:not(.gb-tab-overview) table.gb-blog-table td,
            .gb-main:not(.gb-tab-overview) table.gb-services-table td,
            .gb-main:not(.gb-tab-overview) table.gb-support-table td,
            .gb-main:not(.gb-tab-overview) table.gb-helpdesk-table td {
                padding: 16px 15px !important;
                border-bottom: 1px solid #f1f5f9 !important;
                vertical-align: middle !important;
                font-size: 13px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-filter-bar {
                background: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 12px !important;
                padding: 14px !important;
                margin-bottom: 20px !important;
                gap: 12px !important;
            }

            .gb-main:not(.gb-tab-overview) .gb-input,
            .gb-main:not(.gb-tab-overview) .gb-os-input,
            .gb-main:not(.gb-tab-overview) .gb-search-input,
            .gb-main:not(.gb-tab-overview) .gb-header-select,
            .gb-main:not(.gb-tab-overview) select.gb-input,
            .gb-main:not(.gb-tab-overview) textarea.gb-input {
                border: 1px solid #dbe3ec !important;
                border-radius: 8px !important;
                background: #ffffff !important;
                color: #0f172a !important;
                font-size: 13px !important;
            }

            /* TABLES
               Three listing tables had neither a scroll container nor a minimum
               width, so rather than scrolling they compressed: every cell
               wrapped, names broke across lines and the vetting chips stacked
               into a column. They now scroll, with a floor on the width so the
               columns keep readable proportions at any screen size. */
            .gb-table-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .gb-table-scroll > table { width: 100%; }
            .gb-table-scroll > .gb-table-os { min-width: 1040px; }
            .gb-table-scroll > .gb-blog-table { min-width: 820px; }
            .gb-table-scroll > .gb-booking-table { min-width: 900px; }
            /* Short cells should never wrap - a rate or an action button broken
               over two lines is what made those rows so tall. */
            .gb-main .gb-table-os td:first-child, .gb-main .gb-table-os th:first-child { min-width: 210px; }
            .gb-main .pill-verified, .gb-main .pill-trial, .gb-main .pill-active { white-space: nowrap; }

            /* The native select arrow renders hard against the right border,
               which looked cramped next to the 8px radius. Swap in our own
               chevron with a real gutter and reserve room for it so a long
               option never runs underneath. Applies to every dashboard select,
               not just Eco Products. Listed at both specificities so it also
               beats the rule above, which forces a white background. */
            .gb-main:not(.gb-tab-overview) select.gb-input,
            .gb-main:not(.gb-tab-overview) select.gb-os-input,
            .gb-main:not(.gb-tab-overview) select.gb-header-select,
            .gb-main select.gb-input,
            .gb-main select.gb-os-input,
            .gb-main select.gb-header-select {
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
                background-color: #ffffff !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 14px center !important;
                background-size: 12px 8px !important;
                padding-right: 40px !important;
            }
        </style>
    </main>
</div>

<script>
(function () {
    var btn = document.getElementById('gb-menu-btn');
    var sidebar = document.getElementById('gb-sidebar');
    var backdrop = document.getElementById('gb-sidebar-backdrop');
    if (!btn || !sidebar || !backdrop) { return; }

    function setOpen(open) {
        sidebar.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        backdrop.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    btn.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('is-open'));
    });
    backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) { setOpen(false); btn.focus(); }
    });
    // Leaving mobile width with the drawer open would otherwise lock scrolling.
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024 && sidebar.classList.contains('is-open')) { setOpen(false); }
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
