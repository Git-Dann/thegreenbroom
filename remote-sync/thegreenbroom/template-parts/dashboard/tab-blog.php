<?php $action = isset($_GET['action']) ? $_GET['action'] : ''; ?>

<style>
    /* Green OS Blog Styling - Locked UI */
    .gb-edit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .gb-blog-layout { display: grid; grid-template-columns: 1fr 320px; gap: 30px; align-items: start; }
    .gb-writer-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; }
    .gb-headline-input { width: 100%; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 24px; font-weight: 800; color: #0f172a; box-sizing: border-box; font-family: inherit; }
    .gb-panel { background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
    .gb-panel-head { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 11px; color: #2e7d32; text-transform: uppercase; letter-spacing: 0.5px; }
    .gb-panel-body { padding: 20px; }
    #gb-live-score-wrapper { display: flex; align-items: center; gap: 10px; background: #fff; padding: 5px 15px; border-radius: 50px; border: 1px solid #e2e8f0; }
    .gb-score-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; }
    .gb-seo-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748b; margin-bottom: 10px; }
    .gb-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #e2e8f0; }
    .dot-pass { background: #10b981; }
    .dot-fail { background: #ef4444; }
    .gb-featured-preview { width: 100%; height: 160px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; margin-bottom: 5px; display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer; }
    .gb-featured-preview img { width: 100%; height: 100%; object-fit: cover; }
    .gb-blog-table { width: 100%; border-collapse: collapse; }
    .gb-blog-table th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; }
    .gb-blog-table td { padding: 20px 15px; border-bottom: 1px solid #f1f5f9; }
</style>

<?php if ($action == 'edit'): 
    $pid = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $post = $pid ? get_post($pid) : (object)['ID'=>0, 'post_title'=>'', 'post_content'=>'', 'post_status'=>'draft'];
    $keyword = get_post_meta($pid, '_gb_seo_keyword', true);
    $meta_desc = get_post_meta($pid, '_gb_seo_meta_desc', true);
    $current_score = (int) get_post_meta($pid, '_gb_seo_score', true) ?: 0;
    $image_id = get_post_thumbnail_id($pid);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
?>
    <form method="post" id="gb-blog-form">
        <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="gb_save_action" value="save_post">
        <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
        
        <input type="hidden" name="_thumbnail_id" id="_thumbnail_id" value="<?php echo esc_attr($image_id); ?>">
        <input type="hidden" name="_gb_seo_score" id="gb-seo-score-input" value="<?php echo $current_score; ?>">
        
        <div class="gb-edit-header">
            <div>
                <a href="?tab=blog" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Posts</a>
                <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a;">Blog Editor</h1>
                <div style="margin-top: 10px; display: flex; align-items: center; gap: 15px;">
                    <div id="gb-live-score-wrapper">
                        <span class="gb-score-dot" id="header-score-dot"></span>
                        <span style="font-size: 11px; font-weight: 800; color: #64748b;">SEO Score: <span id="header-score-val"><?php echo $current_score; ?></span>/100</span>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <select name="post_status" class="gb-header-select" style="height:44px; padding:0 15px; border-radius:8px; border:1px solid #e2e8f0; font-weight:600;">
                    <option value="publish" <?php selected($post->post_status, 'publish'); ?>>Publish Live</option>
                    <option value="draft" <?php selected($post->post_status, 'draft'); ?>>Save Draft</option>
                </select>
                <button type="submit" class="gb-header-btn" style="height:44px; background:#2e7d32; color:white; border:none; padding:0 30px; border-radius:8px; font-weight:700; cursor:pointer;">Save Changes</button>
            </div>
        </div>

        <div class="gb-blog-layout">
            <div class="gb-writer-card">
                <div class="gb-headline-wrapper" style="margin-bottom:25px;">
                    <label class="gb-label" style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Article Headline</label>
                    <input type="text" name="post_title" id="gb-post-title" class="gb-headline-input" value="<?php echo esc_attr($post->post_title); ?>">
                </div>
                <?php wp_editor($post->post_content, 'post_content', ['media_buttons' => true, 'textarea_rows' => 25]); ?>
            </div>

            <div class="gb-writer-sidebar">
                <div class="gb-panel">
                    <div class="gb-panel-head">Featured Image</div>
                    <div class="gb-panel-body">
                        <div class="gb-featured-preview" id="gb-preview-wrapper">
                            <?php if($image_url): ?>
                                <img src="<?php echo esc_url($image_url); ?>" id="gb-img-preview">
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:12px; font-weight:600;">+ Click to Upload</span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 11px; color: #94a3b8; margin: 0; text-align: center;">Click box to use media picker</p>
                    </div>
                </div>

                <div class="gb-panel">
                    <div class="gb-panel-head">SEO Optimizer</div>
                    <div class="gb-panel-body">
                        <div style="margin-bottom:15px;">
                            <label class="gb-label" style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Focus Keyword</label>
                            <input type="text" name="_gb_seo_keyword" id="gb-focus-keyword" class="gb-headline-input" style="font-size:14px; padding:10px;" value="<?php echo esc_attr($keyword); ?>">
                        </div>
                        <ul class="gb-seo-list">
                            <li class="gb-seo-item"><span id="check-key-title" class="gb-status-dot"></span> Title</li>
                            <li class="gb-seo-item"><span id="check-key-content" class="gb-status-dot"></span> Content</li>
                            <li class="gb-seo-item"><span id="check-length" class="gb-status-dot"></span> 300+ Words</li>
                            <li class="gb-seo-item"><span id="check-meta" class="gb-status-dot"></span> Meta Desc</li>
                        </ul>
                    </div>
                </div>
                <div class="gb-panel">
                    <div class="gb-panel-head">Search Meta</div>
                    <div class="gb-panel-body">
                        <label class="gb-label" style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:8px;">Meta Description</label>
                        <textarea name="_gb_seo_meta_desc" id="gb-meta-desc" class="gb-headline-input" style="height:80px; font-size:14px; padding:10px; font-weight:400;"><?php echo esc_textarea($meta_desc); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
    jQuery(document).ready(function($){
        $('#gb-preview-wrapper').click(function(e) {
            e.preventDefault();
            var image = wp.media({ title: 'Select Image', multiple: false }).open()
            .on('select', function(e){
                var uploaded = image.state().get('selection').first().toJSON();
                $('#gb-preview-wrapper').html('<img src="'+uploaded.url+'" id="gb-img-preview">');
                $('#_thumbnail_id').val(uploaded.id); // Captures ID for Save
            });
        });

        function runSEO() {
            let kw = $('#gb-focus-keyword').val().toLowerCase();
            let title = $('#gb-post-title').val().toLowerCase();
            let content = (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) ? tinyMCE.activeEditor.getContent({format: 'text'}) : $('#post_content').val();
            let score = 0;
            if(!kw) { $('.gb-status-dot').removeClass('dot-pass dot-fail'); return; }
            if(title.includes(kw)) { $('#check-key-title').addClass('dot-pass'); score += 25; } else { $('#check-key-title').addClass('dot-fail'); }
            if(content.toLowerCase().includes(kw)) { $('#check-key-content').addClass('dot-pass'); score += 25; } else { $('#check-key-content').addClass('dot-fail'); }
            if(content.split(' ').length >= 300) { $('#check-length').addClass('dot-pass'); score += 25; } else { $('#check-length').addClass('dot-fail'); }
            if($('#gb-meta-desc').val().length > 10) { $('#check-meta').addClass('dot-pass'); score += 25; } else { $('#check-meta').addClass('dot-fail'); }
            $('#header-score-val').text(score);
            $('#gb-seo-score-input').val(score);
            $('#header-score-dot').css('background', (score >= 75) ? '#10b981' : ((score >= 50) ? '#f59e0b' : '#ef4444'));
        }
        $('#gb-post-title, #gb-focus-keyword, #gb-meta-desc').on('keyup change blur', runSEO);
        setTimeout(runSEO, 1000);
    });
    </script>
<?php else: 
    $posts = get_posts(['post_type'=>'post', 'posts_per_page'=>20, 'post_status'=>'any']); 
?>
    <div class="gb-edit-header">
        <div>
            <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a;">Blog Articles</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Manage and publish content directly to your site.</p>
        </div>
        <a href="?tab=blog&action=edit&id=0" class="gb-header-btn" style="text-decoration:none; height:44px; display:inline-flex; align-items:center; background:#2e7d32; color:white; padding:0 25px; border-radius:8px; font-weight:700;">+ New Article</a>
    </div>
    <div class="gb-card" style="padding:0; overflow:hidden; border: 1px solid #e2e8f0; border-radius:12px; background: white;">
        <table class="gb-blog-table">
            <thead>
                <tr><th style="width:40%;">Article Title</th><th>SEO Score</th><th>Status</th><th>Date</th><th style="text-align:right;">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($posts as $p): 
                    $score = (int) get_post_meta($p->ID, '_gb_seo_score', true);
                    $score_col = ($score >= 75) ? '#10b981' : (($score >= 50) ? '#f59e0b' : '#ef4444');
                ?>
                <tr>
                    <td><strong style="color:#1b5e20; font-size:15px;"><?php echo $p->post_title ?: '(Untitled)'; ?></strong></td>
                    <td><span style="color:<?php echo $score_col; ?>; font-weight:800; border:1px solid <?php echo $score_col; ?>33; padding:4px 10px; border-radius:20px; font-size:11px;"><?php echo $score; ?> / 100</span></td>
                    <td><span style="background:#f1f5f9; color:#64748b; font-size:10px; padding:3px 8px; border-radius:4px; font-weight:700; text-transform:uppercase;"><?php echo $p->post_status; ?></span></td>
                    <td style="color:#64748b; font-size:13px;"><?php echo get_the_date('M j, Y', $p->ID); ?></td>
                    <td style="text-align:right;">
                        <div style="display:flex; justify-content:flex-end; gap:8px;">
                            <a href="?tab=blog&action=edit&id=<?php echo $p->ID; ?>" class="gb-btn-small-os" style="text-decoration:none; padding:8px 16px; border:1px solid #e2e8f0; border-radius:6px; color:#334155; font-size:12px; font-weight:700;">Edit Article</a>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete_entity', 'id' => $p->ID], home_url('/manager-dashboard')), 'gb_manager_action_delete_entity_' . $p->ID)); ?>" class="gb-btn-small-os" style="text-decoration:none; padding:8px 16px; border:1px solid #fecaca; border-radius:6px; color:#ef4444; font-size:12px; font-weight:700;" onclick="return confirm('Delete this article? It will be moved to trash.');">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
