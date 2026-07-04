<?php 
$action = isset($_GET['action']) ? $_GET['action'] : ''; 
$cleaner_id_filter = isset($_GET['cleaner_id']) ? intval($_GET['cleaner_id']) : 0;
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$orderby = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';
$view = (isset($_GET['view']) && $_GET['view'] === 'archived') ? 'archived' : 'inbox';
?>

<style>
    /* Green OS Support Styling */
    .gb-support-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
    .gb-support-grid { display: grid; grid-template-columns: 1fr 340px; gap: 30px; align-items: start; }
    
    /* Filtering Bar */
    .gb-filter-bar { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 25px; display: flex; gap: 15px; align-items: center; }
    .gb-search-container { flex-grow: 1; position: relative; }
    .gb-search-container .dashicons { position: absolute; left: 12px; top: 12px; color: #94a3b8; font-size: 18px; }
    .gb-search-input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
    
    /* Inbox Table */
    .gb-support-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
    .gb-support-table th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
    .gb-support-table td { padding: 20px 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

    /* Action Buttons - Synced with Blog */
    .gb-btn-small-os { 
        text-decoration: none; padding: 8px 16px; border: 1px solid #e2e8f0; border-radius: 6px; 
        color: #334155; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; 
        transition: all 0.2s ease; background: white; cursor: pointer; text-transform: uppercase;
    }
    .gb-btn-small-os:hover { background: #f8fafc; border-color: #cbd5e1; }
    .gb-btn-reply { background: #2e7d32 !important; color: white !important; border: none !important; }

    /* Avatar & Status */
    .gb-avatar-circle { width: 36px; height: 36px; background: #f1f5f9; border-radius: 50%; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; }
    .status-badge { font-size: 10px; padding: 4px 8px; border-radius: 4px; font-weight: 800; text-transform: uppercase; }
</style>

<?php if ($action == 'view_message' && isset($_GET['id'])): 
    $mid = intval($_GET['id']);
    $msg_post = get_post($mid);
    if (!$msg_post || $msg_post->post_type !== 'gb_message') {
        echo '<div class="gb-card">Message not found.</div>';
        return;
    }
    $related_cleaner_id = get_post_meta($mid, '_gb_related_cleaner', true);
    $related_cleaner_name = $related_cleaner_id ? get_the_title($related_cleaner_id) : 'General Inquiry';
    $sender_email = get_post_meta($mid, '_gb_sender_email', true) ?: 'Guest';
    $sender_name = get_post_meta($mid, '_gb_sender_name', true) ?: 'Unknown Sender';
    $responded_date = get_post_meta($mid, '_gb_responded_date', true);
?>
    <div class="gb-edit-header">
        <div>
            <a href="?tab=support" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Inbox</a>
            <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Message Details</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Review conversation context and respond quickly.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete_message', 'id' => $mid]), 'gb_manager_action_delete_message_' . $mid)); ?>" class="gb-btn-small-os" style="color:#ef4444;" onclick="return confirm('Delete this message?');">Delete</a>
            <?php if(!$responded_date): ?>
                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'mark_replied', 'id' => $mid]), 'gb_manager_action_mark_replied_' . $mid)); ?>" class="gb-btn-small-os">Mark Replied</a>
            <?php endif; ?>
            <?php if (get_post_meta($mid, '_gb_archived', true)): ?>
                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'unarchive_message', 'id' => $mid], home_url('/manager-dashboard')), 'gb_manager_action_unarchive_message_' . $mid)); ?>" class="gb-btn-small-os">Restore</a>
            <?php else: ?>
                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'archive_message', 'id' => $mid], home_url('/manager-dashboard')), 'gb_manager_action_archive_message_' . $mid)); ?>" class="gb-btn-small-os">Archive</a>
            <?php endif; ?>
            <a href="mailto:<?php echo esc_attr($sender_email); ?>" class="gb-btn-small-os gb-btn-reply">Reply via Email ↗</a>
        </div>
    </div>

    <div class="gb-support-grid">
        <div class="gb-card" style="padding:30px;">
            <h3 style="margin-top:0; font-size:20px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">
                <?php echo get_the_title($mid); ?>
            </h3>
            <div style="font-size:15px; line-height:1.7; color:#334155; white-space: pre-wrap;">
                <?php echo esc_html($msg_post->post_content); ?>
            </div>
        </div>

        <div class="gb-card" style="padding:25px;">
            <label class="gb-label">Message Information</label>
            <div style="margin:20px 0;">
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:5px;">Status</div>
                <?php if($responded_date): ?>
                    <span class="status-badge" style="background:#dcfce7; color:#166534;">Replied <?php echo date('M j', strtotime($responded_date)); ?></span>
                <?php else: ?>
                    <span class="status-badge" style="background:#fff7ed; color:#9a3412;">Awaiting Reply</span>
                <?php endif; ?>
            </div>
            <div style="margin-bottom:20px;">
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:5px;">Sent On</div>
                <div style="font-weight:700; color:#0f172a; font-size:14px;"><?php echo get_the_date('F j, Y @ g:i a', $mid); ?></div>
            </div>
            <div style="margin-bottom:20px;">
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:5px;">Sender</div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="gb-avatar-circle"><?php echo strtoupper(substr($sender_name, 0, 1)); ?></div>
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:14px;"><?php echo esc_html($sender_name); ?></div>
                        <div style="color:#64748b; font-size:12px;"><?php echo esc_html($sender_email); ?></div>
                    </div>
                </div>
            </div>
            <div>
                <div style="font-size:11px; color:#94a3b8; text-transform:uppercase; font-weight:700; margin-bottom:5px;">Related To</div>
                <span class="status-badge" style="background:#f1f5f9; color:#475569;"><?php echo esc_html($related_cleaner_name); ?></span>
            </div>
        </div>
    </div>

<?php else: ?>
    
    <div class="booking-controls">
        <div>
            <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;"><?php echo $view === 'archived' ? 'Archived Messages' : 'Support Inbox'; ?></h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;"><?php echo $view === 'archived' ? 'Messages archived automatically after 30 days, or manually.' : 'Manage inquiries and communications from users.'; ?></p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="?tab=support" class="gb-btn-small-os" style="<?php echo $view !== 'archived' ? 'background:#0f172a; color:white; border:none;' : ''; ?>">Inbox</a>
            <a href="?tab=support&view=archived" class="gb-btn-small-os" style="<?php echo $view === 'archived' ? 'background:#0f172a; color:white; border:none;' : ''; ?>">Archived</a>
        </div>
    </div>

    <div class="gb-filter-bar">
        <form method="get" style="display:flex; gap:12px; width:100%; align-items:center;">
            <input type="hidden" name="tab" value="support">
            <div class="gb-search-container">
                <span class="dashicons dashicons-search"></span>
                <input type="text" name="s" class="gb-search-input" value="<?php echo esc_attr($search_query); ?>" placeholder="Search subject or content...">
            </div>
            <select name="cleaner_id" class="gb-input" style="width:200px; padding:10px;">
                <option value="0">All Cleaners</option>
                <?php 
                $cleaners = get_posts(['post_type'=>'gb_cleaner', 'numberposts'=>-1, 'post_status'=>'publish']);
                foreach($cleaners as $c): ?>
                    <option value="<?php echo $c->ID; ?>" <?php selected($cleaner_id_filter, $c->ID); ?>><?php echo esc_html($c->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort" class="gb-input" style="width:160px; padding:10px;">
                <option value="date_desc" <?php selected($orderby, 'date_desc'); ?>>Newest First</option>
                <option value="date_asc" <?php selected($orderby, 'date_asc'); ?>>Oldest First</option>
            </select>
            <button type="submit" class="gb-btn-small-os" style="background:#0f172a; color:white; border:none; padding:11px 20px;">Apply Filter</button>
            <?php if($search_query || $cleaner_id_filter > 0): ?>
                <a href="?tab=support" class="gb-btn-small-os" style="padding:10px;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="gb-card" style="padding:0; overflow:hidden;">
        <table class="gb-booking-table">
            <thead>
                <tr>
                    <th style="width:200px;">Sender</th>
                    <th>Message Content</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:140px;">Received</th>
                    <th style="text-align:right; width:100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $args = [
                    'post_type' => 'gb_message',
                    'posts_per_page' => 20,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => ($orderby == 'date_asc') ? 'ASC' : 'DESC'
                ];
                $meta_query = ['relation' => 'AND'];
                if ($view === 'archived') {
                    $meta_query[] = ['key' => '_gb_archived', 'value' => '1'];
                } else {
                    $meta_query[] = ['key' => '_gb_archived', 'compare' => 'NOT EXISTS'];
                }
                if ($cleaner_id_filter > 0) $meta_query[] = ['key' => '_gb_related_cleaner', 'value' => $cleaner_id_filter];
                $args['meta_query'] = $meta_query;
                if ($search_query) $args['s'] = $search_query;

                $messages = new WP_Query($args);
                if($messages->have_posts()): while($messages->have_posts()): $messages->the_post(); 
                    $cleaner_id = get_post_meta(get_the_ID(), '_gb_related_cleaner', true);
                    $cleaner_name = $cleaner_id ? get_the_title($cleaner_id) : 'General Inquiry';
                    $sender = get_post_meta(get_the_ID(), '_gb_sender_email', true) ?: 'Guest';
                    $sender_name = get_post_meta(get_the_ID(), '_gb_sender_name', true) ?: $sender;
                    $responded = get_post_meta(get_the_ID(), '_gb_responded_date', true);
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div class="gb-avatar-circle"><?php echo strtoupper(substr($sender_name, 0, 1)); ?></div>
                            <div style="overflow:hidden;">
                                <div style="font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:14px;"><?php echo esc_html($sender_name); ?></div>
                                <div style="font-size:11px; color:#94a3b8;"><?php echo esc_html($sender); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="margin-bottom:5px;">
                            <span class="status-badge" style="background:#f1f5f9; color:#64748b; font-size:9px;">To: <?php echo esc_html($cleaner_name); ?></span>
                        </div>
                        <div style="font-weight:700; color:#1b5e20; font-size:13px; margin-bottom:2px;"><?php the_title(); ?></div>
                        <div style="color:#64748b; font-size:12px;"><?php echo wp_trim_words(get_the_content(), 10); ?></div>
                    </td>
                    <td>
                        <?php if($responded): ?>
                            <span style="color:#166534; font-size:11px; font-weight:700;">✅ Replied</span>
                        <?php else: ?>
                            <span style="color:#f59e0b; font-size:11px; font-weight:700;">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#94a3b8; font-size:12px; font-weight:500;">
                        <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')); ?> ago
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex; justify-content:flex-end; gap:6px;">
                            <a href="?tab=support&action=view_message&id=<?php echo get_the_ID(); ?>" class="gb-btn-small-os">View</a>
                            <?php if ($view === 'archived'): ?>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'unarchive_message', 'id' => get_the_ID()], home_url('/manager-dashboard')), 'gb_manager_action_unarchive_message_' . get_the_ID())); ?>" class="gb-btn-small-os">Restore</a>
                            <?php else: ?>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'archive_message', 'id' => get_the_ID()], home_url('/manager-dashboard')), 'gb_manager_action_archive_message_' . get_the_ID())); ?>" class="gb-btn-small-os">Archive</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:80px 0;"><h3 style="color:#94a3b8;">No messages found</h3></td></tr>
                <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
