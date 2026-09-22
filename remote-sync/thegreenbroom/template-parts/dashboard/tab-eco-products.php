<?php
/**
 * Eco Products catalogue.
 *
 * The page used to be two hard-coded cards edited as page meta, which does not
 * scale past a handful of entries. Each product is now its own post, so the
 * catalogue can grow to hundreds of documents with search and paging.
 */

$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
?>

<style>
    .gb-eco-toolbar { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:24px; flex-wrap:wrap; }
    .gb-eco-summary { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .gb-eco-summary span { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border:1px solid #e2e8f0; border-radius:999px; background:#fff; color:#64748b; font-size:11px; font-weight:700; }
    .gb-eco-summary strong { color:#0f172a; }
    .gb-eco-search { display:flex; gap:8px; align-items:center; margin-bottom:20px; }
    .gb-eco-search input[type=search] { flex:1 1 260px; max-width:360px; height:42px; padding:0 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; box-sizing:border-box; }
    .gb-eco-btn { text-decoration:none; padding:9px 14px; border:1px solid #e2e8f0; border-radius:8px; color:#334155; font-size:12px; font-weight:700; background:#fff; cursor:pointer; display:inline-flex; align-items:center; }
    .gb-eco-btn:hover { background:#f8fafc; border-color:#cbd5e1; }
    .gb-eco-btn.danger { color:#b91c1c; border-color:#fecaca; }
    .gb-eco-btn.danger:hover { background:#fef2f2; }
    .gb-eco-primary { background:#2e7d32; color:#fff; border:none; padding:11px 20px; border-radius:8px; font-weight:700; text-decoration:none; font-size:13px; display:inline-flex; align-items:center; cursor:pointer; }

    .gb-eco-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:18px; }
    .gb-eco-card { display:flex; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,.05); }
    .gb-eco-card-media { height:132px; background:#f0fdf4; display:flex; align-items:center; justify-content:center; border-bottom:1px solid #f1f5f9; overflow:hidden; }
    .gb-eco-card-media img { width:100%; height:100%; object-fit:cover; display:block; }
    .gb-eco-card-media .dashicons { font-size:34px; width:34px; height:34px; color:#86bf8a; }
    .gb-eco-card-body { padding:16px; flex:1 1 auto; min-width:0; }
    .gb-eco-card-body h2 { margin:0 0 6px; font-size:15px; line-height:1.35; color:#1b5e20; word-wrap:break-word; }
    .gb-eco-card-body p { margin:0; color:#64748b; font-size:12px; line-height:1.5; }
    .gb-eco-card-meta { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
    .gb-eco-chip { padding:4px 9px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
    .gb-eco-chip.ok { background:#dcfce7; color:#166534; }
    .gb-eco-chip.muted { background:#f1f5f9; color:#475569; }
    .gb-eco-chip.warn { background:#fffbeb; color:#92400e; }
    .gb-eco-card-actions { display:flex; gap:8px; padding:12px 16px; border-top:1px solid #f1f5f9; background:#fbfdfb; }
    /* flex-basis 0 + min-width 0 keeps Edit/View/Delete on one row at any card
       width; a basis sized to the label wrapped Delete onto its own line. */
    .gb-eco-card-actions .gb-eco-btn { flex:1 1 0; min-width:0; padding:9px 6px; justify-content:center; white-space:nowrap; }
    .gb-eco-empty { padding:55px 20px; text-align:center; background:#fff; border:1px solid #e2e8f0; border-radius:12px; color:#64748b; }
    .gb-eco-pager { display:flex; justify-content:center; gap:6px; margin-top:24px; flex-wrap:wrap; }
    .gb-eco-pager a, .gb-eco-pager span { padding:8px 13px; border-radius:7px; border:1px solid #e2e8f0; background:#fff; color:#334155; font-size:12px; font-weight:700; text-decoration:none; }
    .gb-eco-pager span.current { background:#2e7d32; border-color:#2e7d32; color:#fff; }

    .gb-eco-file-row { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:14px; border:1px dashed #cbd5e1; border-radius:10px; background:#f8fafc; }
    .gb-eco-file-state { flex:1 1 220px; min-width:0; font-size:12px; color:#475569; word-break:break-all; }
    .gb-eco-thumb-preview { width:120px; height:80px; border:1px solid #e2e8f0; border-radius:8px; background:#f0fdf4 center/cover no-repeat; }
</style>

<?php if ($action === 'edit'):
    $pid  = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $post = $pid ? get_post($pid) : null;
    if ($post && $post->post_type !== 'gb_eco_product') {
        $post = null;
        $pid  = 0;
    }

    $title   = $post ? $post->post_title : '';
    $excerpt = $post ? $post->post_excerpt : '';
    $body    = $post ? $post->post_content : '';
    $order   = $post ? (int) $post->menu_order : 0;
    $status  = $post ? get_post_status($pid) : 'publish';
    if (!in_array($status, ['publish', 'draft'], true)) {
        $status = 'publish';
    }
    $file_id   = $pid ? (int) get_post_meta($pid, '_gb_eco_file', true) : 0;
    $file_url  = $file_id ? wp_get_attachment_url($file_id) : '';
    $file_name = $file_id ? basename((string) get_attached_file($file_id)) : '';
    $link      = $pid ? trim((string) get_post_meta($pid, '_gb_eco_link', true)) : '';
    $cta_label  = $pid && function_exists('gb_get_eco_product_cta_label') ? gb_get_eco_product_cta_label($pid) : 'View guide';
    $soon_label = $pid && function_exists('gb_get_eco_product_soon_label') ? gb_get_eco_product_soon_label($pid) : 'Coming soon';
    $thumb_id  = $pid ? (int) get_post_thumbnail_id($pid) : 0;
    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
?>
    <form method="post">
        <input type="hidden" name="post_id" value="<?php echo (int) $pid; ?>">
        <input type="hidden" name="gb_save_action" value="save_eco_product">
        <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

        <div class="gb-edit-header">
            <div>
                <a href="?tab=eco-products" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">&larr; Back to Eco Products</a>
                <h1 class="gb-title" style="margin:10px 0 0;"><?php echo $pid ? 'Edit Eco Product' : 'Add Eco Product'; ?></h1>
                <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Title, description and a file or link. Published products appear on the Eco Products page.</p>
            </div>
            <button type="submit" class="gb-eco-primary" style="height:44px;">Save Product</button>
        </div>

        <div class="gb-config-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:30px;">
            <div style="display:grid; grid-template-columns:1fr 220px; gap:20px;">
                <div class="gb-form-row">
                    <label class="gb-label">Title</label>
                    <input type="text" name="post_title" class="gb-input" value="<?php echo esc_attr($title); ?>" placeholder="e.g. Eco Cleaning Products Guide" required>
                </div>
                <div class="gb-form-row">
                    <label class="gb-label">Status</label>
                    <select name="post_status" class="gb-input" style="height:44px;">
                        <option value="publish" <?php selected($status, 'publish'); ?>>Published</option>
                        <option value="draft" <?php selected($status, 'draft'); ?>>Hidden (draft)</option>
                    </select>
                </div>
            </div>

            <div class="gb-form-row">
                <label class="gb-label">Card description</label>
                <textarea name="post_excerpt" class="gb-textarea" rows="3" style="height:auto;" placeholder="One or two lines, shown on the card on the Eco Products page."><?php echo esc_textarea($excerpt); ?></textarea>
            </div>

            <div class="gb-form-row">
                <label class="gb-label">Full guide (optional)</label>
                <?php
                wp_editor($body, 'gb_eco_body', array(
                    'textarea_name' => 'post_content',
                    'textarea_rows' => 16,
                    'media_buttons' => true,
                ));
                ?>
                <p style="color:#94a3b8; font-size:12px; margin-top:8px;">
                    Write the guide here and it becomes a proper web page on the site, which the card links to.
                    This is the best option for anything customers should read: it works on a phone and Google can index it.
                    If you also attach a file below, it appears as a &ldquo;Download the full guide&rdquo; button on that page.
                    Leave this blank and the card links straight to the file or link instead.
                </p>
            </div>

            <div class="gb-form-row" id="gb-eco-file">
                <label class="gb-label">Document / file</label>
                <div class="gb-eco-file-row">
                    <input type="hidden" name="_gb_eco_file" id="gb-eco-file-id" value="<?php echo (int) $file_id; ?>">
                    <span class="gb-eco-file-state" id="gb-eco-file-state">
                        <?php if ($file_url): ?>
                            Attached: <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($file_name ?: $file_url); ?></a>
                        <?php else: ?>
                            No file attached yet.
                        <?php endif; ?>
                    </span>
                    <button type="button" class="gb-eco-btn" id="gb-eco-file-pick">Upload / choose file</button>
                    <button type="button" class="gb-eco-btn danger" id="gb-eco-file-clear" <?php echo $file_id ? '' : 'hidden'; ?>>Remove file</button>
                </div>
                <p style="color:#94a3b8; font-size:12px; margin-top:8px;">
                    Optional. If the guide above is written, this becomes a download button on its page.
                    If it is blank, the card opens this file directly &mdash; avoid Word documents and ZIP archives for that,
                    since many phones cannot open them.
                </p>
            </div>

            <div class="gb-form-row">
                <label class="gb-label">Or link to a URL (used only when no file is attached)</label>
                <input type="text" name="_gb_eco_link" class="gb-input" value="<?php echo esc_attr($link); ?>" placeholder="/make-your-own or https://...">
                <p style="color:#94a3b8; font-size:12px; margin-top:8px;">Leave both the file and the link empty to show the card as unavailable, using the wording below.</p>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px;">
                <div class="gb-form-row">
                    <label class="gb-label">Button text (when a file or link is set)</label>
                    <input type="text" name="_gb_eco_cta_label" class="gb-input" value="<?php echo esc_attr($cta_label); ?>" placeholder="View guide">
                </div>
                <div class="gb-form-row">
                    <label class="gb-label">Text when nothing is attached yet</label>
                    <input type="text" name="_gb_eco_soon_label" class="gb-input" value="<?php echo esc_attr($soon_label); ?>" placeholder="Coming soon">
                    <p style="color:#94a3b8; font-size:12px; margin-top:8px;">Shown instead of a button, e.g. &ldquo;Coming soon&rdquo; or &ldquo;Members only&rdquo;.</p>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 220px; gap:25px; align-items:start;">
                <div class="gb-form-row" id="gb-eco-thumb">
                    <label class="gb-label">Card image (optional)</label>
                    <input type="hidden" name="gb_eco_thumb_id" id="gb-eco-thumb-id" value="<?php echo (int) $thumb_id; ?>">
                    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <span class="gb-eco-thumb-preview" id="gb-eco-thumb-preview" style="<?php echo $thumb_url ? 'background-image:url(' . esc_url($thumb_url) . ');' : ''; ?>"></span>
                        <span style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" class="gb-eco-btn" id="gb-eco-thumb-pick">Choose image</button>
                            <button type="button" class="gb-eco-btn danger" id="gb-eco-thumb-clear" <?php echo $thumb_id ? '' : 'hidden'; ?>>Remove</button>
                        </span>
                    </div>
                </div>
                <div class="gb-form-row">
                    <label class="gb-label">Sort order</label>
                    <input type="number" name="menu_order" class="gb-input" value="<?php echo (int) $order; ?>" step="1">
                    <p style="color:#94a3b8; font-size:12px; margin-top:8px;">Lower numbers appear first.</p>
                </div>
            </div>
        </div>
    </form>

    <script>
    jQuery(document).ready(function ($) {
        function picker(opts) {
            return wp.media({ title: opts.title, button: { text: opts.button }, library: opts.library, multiple: false });
        }

        $('#gb-eco-file-pick').on('click', function (e) {
            e.preventDefault();
            var frame = picker({ title: 'Select or upload a document', button: 'Use this file' });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $('#gb-eco-file-id').val(att.id);
                $('#gb-eco-file-state').text('Attached: ' + (att.filename || att.title)).css('color', '#166534');
                $('#gb-eco-file-clear').prop('hidden', false);
            });
            frame.open();
        });

        $('#gb-eco-file-clear').on('click', function (e) {
            e.preventDefault();
            $('#gb-eco-file-id').val('');
            $('#gb-eco-file-state').text('No file attached yet.').css('color', '#475569');
            $(this).prop('hidden', true);
        });

        $('#gb-eco-thumb-pick').on('click', function (e) {
            e.preventDefault();
            var frame = picker({ title: 'Select a card image', button: 'Use this image', library: { type: 'image' } });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
                $('#gb-eco-thumb-id').val(att.id);
                $('#gb-eco-thumb-preview').css('background-image', 'url(' + url + ')');
                $('#gb-eco-thumb-clear').prop('hidden', false);
            });
            frame.open();
        });

        $('#gb-eco-thumb-clear').on('click', function (e) {
            e.preventDefault();
            $('#gb-eco-thumb-id').val('');
            $('#gb-eco-thumb-preview').css('background-image', 'none');
            $(this).prop('hidden', true);
        });
    });
    </script>

<?php else:
    $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged    = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $per_page = 24;

    $eco_query = new WP_Query([
        'post_type'      => 'gb_eco_product',
        'post_status'    => ['publish', 'draft'],
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        's'              => $search,
        'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
    ]);

    $counts     = wp_count_posts('gb_eco_product');
    $live_total = isset($counts->publish) ? (int) $counts->publish : 0;
    $draft_total = isset($counts->draft) ? (int) $counts->draft : 0;
?>
    <div class="gb-eco-toolbar">
        <div>
            <h1 class="gb-title">Eco Products</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Guides and documents shown on the public Eco Products page. Add as many as you need.</p>
            <div class="gb-eco-summary" aria-label="Eco product summary">
                <span><strong><?php echo esc_html($live_total); ?></strong> published</span>
                <span><strong><?php echo esc_html($draft_total); ?></strong> hidden</span>
                <span><strong><?php echo esc_html($live_total + $draft_total); ?></strong> total</span>
            </div>
        </div>
        <a href="?tab=eco-products&action=edit&id=0" class="gb-eco-primary">+ Add Eco Product</a>
    </div>

    <form method="get" class="gb-eco-search">
        <input type="hidden" name="tab" value="eco-products">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search products by title or description">
        <button type="submit" class="gb-eco-btn">Search</button>
        <?php if ($search !== ''): ?>
            <a href="?tab=eco-products" class="gb-eco-btn">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($eco_query->have_posts()): ?>
        <div class="gb-eco-cards">
            <?php while ($eco_query->have_posts()): $eco_query->the_post();
                $eid        = get_the_ID();
                $eco_status = get_post_status($eid);
                $eco_link   = function_exists('gb_get_eco_product_link') ? gb_get_eco_product_link($eid) : '';
                $has_file   = (int) get_post_meta($eid, '_gb_eco_file', true) > 0;
                $thumb      = get_the_post_thumbnail_url($eid, 'medium');
                $delete_url = wp_nonce_url(
                    add_query_arg(['action' => 'delete_entity', 'id' => $eid], home_url('/manager-dashboard')),
                    'gb_manager_action_delete_entity_' . $eid
                );
            ?>
                <article class="gb-eco-card">
                    <div class="gb-eco-card-media">
                        <?php if ($thumb): ?>
                            <img src="<?php echo esc_url($thumb); ?>" alt="">
                        <?php else: ?>
                            <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <div class="gb-eco-card-body">
                        <h2><?php the_title(); ?></h2>
                        <p><?php
                            $eco_card_desc = trim((string) get_the_excerpt());
                            if ($eco_card_desc === '') { $eco_card_desc = wp_strip_all_tags(get_the_content()); }
                            echo esc_html(wp_trim_words($eco_card_desc, 18));
                        ?></p>
                        <div class="gb-eco-card-meta">
                            <?php if ($eco_status === 'publish'): ?>
                                <span class="gb-eco-chip ok">Published</span>
                            <?php else: ?>
                                <span class="gb-eco-chip muted">Hidden</span>
                            <?php endif; ?>
                            <?php if (function_exists('gb_eco_product_has_guide') && gb_eco_product_has_guide($eid)): ?>
                                <span class="gb-eco-chip ok">Web page</span>
                                <?php if ($has_file): ?><span class="gb-eco-chip muted">+ download</span><?php endif; ?>
                            <?php elseif ($has_file): ?>
                                <span class="gb-eco-chip ok">File</span>
                            <?php elseif ($eco_link !== ''): ?>
                                <span class="gb-eco-chip ok">Link</span>
                            <?php else: ?>
                                <span class="gb-eco-chip warn"><?php echo esc_html(function_exists('gb_get_eco_product_soon_label') ? gb_get_eco_product_soon_label($eid) : 'Coming soon'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="gb-eco-card-actions">
                        <a href="?tab=eco-products&action=edit&id=<?php echo (int) $eid; ?>" class="gb-eco-btn">Edit</a>
                        <?php if ($eco_link !== ''): ?>
                            <a href="<?php echo esc_url($eco_link); ?>" target="_blank" rel="noopener" class="gb-eco-btn">View</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($delete_url); ?>" class="gb-eco-btn danger" onclick="return confirm('Delete this product? It will be moved to trash.');">Delete</a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php
        $total_pages = (int) $eco_query->max_num_pages;
        if ($total_pages > 1):
            $base = add_query_arg(['tab' => 'eco-products', 's' => $search], home_url('/manager-dashboard/'));
        ?>
            <nav class="gb-eco-pager" aria-label="Eco product pages">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $paged): ?>
                        <span class="current"><?php echo (int) $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo esc_url(add_query_arg('paged', $i, $base)); ?>"><?php echo (int) $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="gb-eco-empty">
            <h3 style="margin:0 0 6px; color:#475569;"><?php echo $search !== '' ? 'No products match that search' : 'No eco products yet'; ?></h3>
            <p style="margin:0;"><?php echo $search !== '' ? 'Try a different term, or clear the search.' : 'Add your first guide or document to fill the Eco Products page.'; ?></p>
        </div>
    <?php endif; wp_reset_postdata(); ?>
<?php endif; ?>
