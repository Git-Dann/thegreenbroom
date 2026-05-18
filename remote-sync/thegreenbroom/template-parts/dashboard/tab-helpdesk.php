<?php
/**
 * Tab Content: Support Helpdesk
 * Synced with tab-bookings.php Table UI
 * Integrated with tab-support.php Filtering & Search
 */

// 1. GET FILTER PARAMETERS
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$orderby = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';

// 2. HANDLE RESOLVE ACTION
if (isset($_POST['gb_resolve_ticket'])) {
    $ticket_id_to_resolve = intval($_POST['ticket_id']);
    if (
        !empty($_POST['gb_helpdesk_nonce']) &&
        wp_verify_nonce($_POST['gb_helpdesk_nonce'], 'gb_helpdesk_resolve_' . $ticket_id_to_resolve) &&
        get_post_type($ticket_id_to_resolve) === 'gb_message'
    ) {
        update_post_meta($ticket_id_to_resolve, '_gb_helpdesk_status', 'resolved');
        echo '<div style="background:#dcfce7; border:1px solid #166534; color:#166534; padding:15px; border-radius:12px; margin-bottom:25px; font-weight:700; font-size:14px;">✅ Ticket marked as resolved.</div>';
    } else {
        echo '<div style="background:#fee2e2; border:1px solid #991b1b; color:#991b1b; padding:15px; border-radius:12px; margin-bottom:25px; font-weight:700; font-size:14px;">Security validation failed.</div>';
    }
}
?>

<style>
    /* --- SYNCED STYLES FROM TAB-BOOKINGS.PHP & TAB-SUPPORT.PHP --- */
    .gb-helpdesk-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .gb-helpdesk-table th { text-align: left; padding: 12px 15px; border-bottom: 2px solid #f1f5f9; color: #64748b; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
    .gb-helpdesk-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    
    .gb-filter-bar { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 25px; display: flex; gap: 15px; align-items: center; }
    .gb-search-container { flex-grow: 1; position: relative; }
    .gb-search-container .dashicons { position: absolute; left: 12px; top: 12px; color: #94a3b8; font-size: 18px; }
    .gb-search-input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
    
    .btn-os { background: #ffffff; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #334155; text-decoration: none; display: inline-block; }
    .btn-os.active { background: #2e7d32; color: #fff; border-color: #2e7d32; }

    .gb-btn-small-os { background: #ffffff; border: 1px solid #e2e8f0; padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #334155; text-decoration: none; cursor: pointer; }
    .gb-btn-primary-os { background: #2e7d32; color: #fff; border: 1px solid #2e7d32; padding: 8px 15px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    
    .gb-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.8); z-index:99999; align-items:center; justify-content:center; padding:20px; text-align:left; backdrop-filter: blur(4px); }
</style>

<div class="booking-controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h1 class="gb-title" style="font-size: 28px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -1px;">Support Helpdesk</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Review and resolve support tickets raised by cleaners and customers.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="?tab=helpdesk&status=active" class="btn-os <?php echo $status_filter == 'active' ? 'active' : ''; ?>">Active</a>
        <a href="?tab=helpdesk&status=resolved" class="btn-os <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">Resolved</a>
    </div>
</div>

<div class="gb-filter-bar">
    <form method="get" style="display:flex; gap:12px; width:100%; align-items:center;">
        <input type="hidden" name="tab" value="helpdesk">
        <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
        
        <div class="gb-search-container">
            <span class="dashicons dashicons-search"></span>
            <input type="text" name="s" class="gb-search-input" value="<?php echo esc_attr($search_query); ?>" placeholder="Search subject or sender...">
        </div>
        
        <select name="sort" class="gb-input" style="width:160px; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
            <option value="date_desc" <?php selected($orderby, 'date_desc'); ?>>Newest First</option>
            <option value="date_asc" <?php selected($orderby, 'date_asc'); ?>>Oldest First</option>
        </select>
        
        <button type="submit" class="gb-btn-small-os" style="background:#0f172a; color:white; border:none; padding:11px 20px;">Apply</button>
        <?php if($search_query): ?>
            <a href="?tab=helpdesk&status=<?php echo $status_filter; ?>" class="gb-btn-small-os">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="gb-card" style="padding:0; overflow:hidden; background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);">
    <table class="gb-helpdesk-table">
        <thead>
            <tr>
                <th>Received</th>
                <th>Sender</th>
                <th>Subject & Preview</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $args = [
                'post_type' => 'gb_message',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'order' => ($orderby == 'date_asc') ? 'ASC' : 'DESC',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_gb_is_helpdesk',
                        'value' => '1'
                    ]
                ]
            ];

            // Filter by Resolved Status
            if ($status_filter == 'resolved') {
                $args['meta_query'][] = [
                    'key' => '_gb_helpdesk_status',
                    'value' => 'resolved'
                ];
            } else {
                $args['meta_query'][] = [
                    'key' => '_gb_helpdesk_status',
                    'value' => 'resolved',
                    'compare' => 'NOT EXISTS'
                ];
            }

            if ($search_query) {
                $args['s'] = $search_query;
            }

            $messages = new WP_Query($args);

            if($messages->have_posts()): while($messages->have_posts()): $messages->the_post(); 
                $sender_name = get_post_meta(get_the_ID(), '_gb_sender_name', true);
                $sender_email = get_post_meta(get_the_ID(), '_gb_sender_email', true);
                $ticket_id = get_the_ID();
                $content = get_the_content();
                ?>
                <tr onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='transparent'" style="transition: background 0.2s;">
                    <td>
                        <div style="font-weight:700; color:#1b5e20;"><?php echo get_the_date('M j, Y'); ?></div>
                        <div style="color:#94a3b8; font-size:11px;"><?php echo get_the_time(); ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600; color: #0f172a;"><?php echo esc_html($sender_name); ?></div>
                        <div style="color:#94a3b8; font-size:12px;"><?php echo esc_html($sender_email); ?></div>
                    </td>
                    <td>
                        <div style="font-weight:700; color:#334155; margin-bottom: 4px;"><?php the_title(); ?></div>
                        <div style="color:#64748b; font-size:12px; line-height:1.4; max-width: 350px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo wp_strip_all_tags($content); ?>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <div style="display: inline-flex; gap: 8px; align-items: center;">
                            <button onclick="openMessageModal(<?php echo $ticket_id; ?>)" class="gb-btn-small-os">View</button>
                            
                            <?php if($status_filter != 'resolved'): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                <?php wp_nonce_field('gb_helpdesk_resolve_' . $ticket_id, 'gb_helpdesk_nonce'); ?>
                                <button type="submit" name="gb_resolve_ticket" class="gb-btn-small-os" style="color: #2e7d32; border-color: #2e7d32;">Resolve</button>
                            </form>
                            <?php endif; ?>

                            <a href="mailto:<?php echo esc_attr($sender_email); ?>?subject=Re: <?php echo rawurlencode(get_the_title()); ?>" class="gb-btn-primary-os">
                                <span class="dashicons dashicons-undo" style="font-size: 14px; width: 14px; height: 14px; margin-top: 1px;"></span> Reply
                            </a>
                        </div>

                        <div id="msg-modal-<?php echo $ticket_id; ?>" class="gb-modal-overlay">
                            <div style="background:white; width:100%; max-width:550px; border-radius:20px; padding:40px; position:relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                                <button onclick="openMessageModal(<?php echo $ticket_id; ?>)" style="position:absolute; top:24px; right:24px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
                                
                                <span style="display:inline-block; background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:6px; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:16px; letter-spacing:0.05em;">Support Inquiry</span>
                                <h3 style="margin:0 0 8px; font-size:24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;"><?php the_title(); ?></h3>
                                <p style="margin:0 0 32px; color:#64748b; font-size:14px;">From: <strong><?php echo esc_html($sender_name); ?></strong></p>
                                
                                <div style="background:#f8fafc; padding:24px; border-radius:12px; color:#334155; line-height:1.7; font-size:15px; margin-bottom:32px; border:1px solid #f1f5f9; white-space: pre-wrap;"><?php echo esc_html($content); ?></div>
                                
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                                    <?php if($status_filter != 'resolved'): ?>
                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                        <?php wp_nonce_field('gb_helpdesk_resolve_' . $ticket_id, 'gb_helpdesk_nonce'); ?>
                                        <button type="submit" name="gb_resolve_ticket" class="gb-btn-small-os" style="width:100%; height: 48px; border-color: #2e7d32; color: #2e7d32; font-size: 14px;">Resolve Ticket</button>
                                    </form>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                    <a href="mailto:<?php echo esc_attr($sender_email); ?>?subject=Re: <?php echo rawurlencode(get_the_title()); ?>" class="gb-btn-primary-os" style="justify-content:center; height: 48px; font-size: 14px;">Send Reply</a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endwhile; wp_reset_postdata(); else : ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding:80px 24px; color:#94a3b8; font-size: 15px;">No matching inquiries found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function openMessageModal(id) {
    const modal = document.getElementById('msg-modal-' + id);
    modal.style.display = (modal.style.display === 'none' || modal.style.display === '') ? 'flex' : 'none';
}
</script>
