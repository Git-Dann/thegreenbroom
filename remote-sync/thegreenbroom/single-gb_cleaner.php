<?php
/* Template Name: Cleaner Profile (Single) */
get_header(); 

$cleaner_id = get_the_ID();
$rate       = get_post_meta($cleaner_id, 'hourly_rate', true) ?: '15';
$verified   = get_post_meta($cleaner_id, 'is_verified', true);
$location   = get_post_meta($cleaner_id, 'location', true) ?: 'Manchester, UK';
$review_count = get_comments_number($cleaner_id);
?>

<style>
    /* PAGE LAYOUT - Updated to Brand Green */
    .gb-profile-header { background: #2e7d32; color: white; padding: 80px 20px 100px; text-align: center; position: relative; }
    .gb-profile-avatar { width: 140px; height: 140px; border-radius: 50%; border: 4px solid white; object-fit: cover; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); background: #fff; }
    
    /* Name - Forced White */
    .gb-profile-name { font-size: 36px; font-weight: 800; margin: 0; color: #ffffff !important; }
    
    /* Verified Badge - White Pill for contrast */
    .gb-verified-badge { 
        background: #ffffff; 
        color: #2e7d32; 
        padding: 8px 16px; 
        border-radius: 20px; 
        font-size: 13px; 
        font-weight: 700; 
        text-transform: uppercase; 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        margin-top: 15px; 
        letter-spacing: 0.5px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .gb-verified-badge img { height: 14px; width: auto; }
    
    .gb-profile-container { max-width: 1000px; margin: -60px auto 60px; padding: 0 20px; position: relative; z-index: 10; }
    .gb-profile-card { background: white; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; display: grid; grid-template-columns: 2fr 1fr; }
    
    /* CONTENT & SIDEBAR */
    .gb-profile-content { padding: 50px; }
    .gb-profile-sidebar { background: #f8fafc; padding: 50px 30px; border-left: 1px solid #e2e8f0; text-align: center; }
    .gb-section-title { font-size: 18px; font-weight: 800; color: #1d1d1f; margin: 0 0 20px; text-transform: uppercase; letter-spacing: 0.5px; }
    .gb-bio-text { line-height: 1.8; color: #334155; font-size: 16px; margin-bottom: 40px; }
    
    /* SERVICES LIST - Green Checks */
    .gb-services-list { list-style: none; padding: 0; margin: 0 0 40px 0; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .gb-services-list li { display: flex; align-items: center; gap: 10px; font-size: 15px; color: #475569; font-weight: 500; }
    .gb-check-icon { color: #2e7d32; font-weight: bold; }

    /* REVIEWS STYLING */
    .gb-review-card { background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid #f1f5f9; position: relative; text-align: left; }
    .gb-rev-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .gb-rev-author { font-weight: 700; color: #1d1d1f; font-size: 15px; display: flex; align-items: center; gap: 8px; }
    .gb-rev-verified { background: #dcfce7; color: #166534; font-size: 10px; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
    .gb-rev-date { font-size: 12px; color: #94a3b8; }
    .gb-rev-body { font-size: 14px; color: #475569; line-height: 1.6; font-style: italic; }
    .gb-star-row { color: #fbbf24; font-size: 12px; margin-bottom: 8px; display: block; text-align: left; }

    /* BUTTONS - Brand Green */
    .gb-price-tag { font-size: 42px; font-weight: 800; color: #1d1d1f; display: block; line-height: 1; }
    .gb-price-sub { font-size: 14px; color: #64748b; margin-bottom: 30px; display: block; font-weight: 500; }
    .gb-btn-book { display: block; width: 100%; background: #2e7d32; color: white; padding: 18px; border-radius: 10px; font-weight: 700; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2); font-size: 16px; margin-bottom: 15px; border:none; cursor: pointer; }
    .gb-btn-book:hover { background: #1b5e20; transform: translateY(-2px); }
    .gb-btn-msg { display: block; width: 100%; background: white; color: #1d1d1f; border: 2px solid #e2e8f0; padding: 16px; border-radius: 10px; font-weight: 700; text-decoration: none; transition: 0.2s; font-size: 15px; box-sizing: border-box; cursor: pointer; }
    .gb-btn-msg:hover { border-color: #cbd5e1; background: #f1f5f9; }

    /* MODAL */
    .gb-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .gb-modal-content { background: white; padding: 40px; border-radius: 16px; width: 100%; max-width: 450px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideUp 0.3s ease; text-align: left; }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .gb-close-modal { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #94a3b8; }
    .gb-modal input, .gb-modal textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box; }
    .gb-modal textarea { height: 120px; resize: none; }
    .gb-modal label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #334155; }

    @media (max-width: 800px) { .gb-profile-card { grid-template-columns: 1fr; } .gb-profile-sidebar { border-left: none; border-top: 1px solid #e2e8f0; } }
</style>

<div class="gb-profile-header">
    <?php if(has_post_thumbnail()): ?>
        <?php the_post_thumbnail('medium', ['class' => 'gb-profile-avatar']); ?>
    <?php else: ?>
        <img src="https://www.gravatar.com/avatar/?d=mp&s=150" class="gb-profile-avatar" alt="Cleaner Avatar">
    <?php endif; ?>
    
    <h1 class="gb-profile-name"><?php the_title(); ?></h1>
    <?php if($verified): ?>
        <span class="gb-verified-badge">
            <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-icon-logo.png">
            Verified Professional
        </span>
    <?php endif; ?>
</div>

<div class="gb-profile-container">
    <div class="gb-profile-card">
        
        <div class="gb-profile-content">
            <h3 class="gb-section-title">About Me</h3>
            <div class="gb-bio-text">
                <?php the_content(); ?>
                <?php if(empty(get_the_content())): ?>
                    <p>I am a verified professional with The Green Broom. I specialize in eco-friendly cleaning and take pride in delivering a spotless home.</p>
                <?php endif; ?>
            </div>

            <h3 class="gb-section-title">My Services</h3>
            <ul class="gb-services-list">
                <li><span class="gb-check-icon">✓</span> Standard Cleaning</li>
                <li><span class="gb-check-icon">✓</span> Deep Cleaning</li>
                <li><span class="gb-check-icon">✓</span> Move In/Out</li>
                <li><span class="gb-check-icon">✓</span> Eco Supplies Included</li>
            </ul>
            
            <p style="color:#64748b; margin:0 0 40px;">📍 Service Area: <strong><?php echo esc_html($location); ?></strong></p>
            
            <hr style="border:0; border-top:1px solid #e2e8f0; margin-bottom:40px;">

            <h3 class="gb-section-title">Client Reviews (<?php echo $review_count; ?>)</h3>
            
            <div class="gb-reviews-list">
                <?php 
                $comments = get_comments(['post_id' => $cleaner_id, 'status' => 'approve']);
                if ( $comments ) :
                    foreach ( $comments as $comment ) : 
                        $c_email = $comment->comment_author_email;
                        $is_client = gb_is_verified_client($c_email, $cleaner_id); 
                ?>
                    <div class="gb-review-card">
                        <div class="gb-rev-header">
                            <div class="gb-rev-author">
                                <?php echo get_comment_author($comment); ?>
                                <?php if($is_client): ?>
                                    <span class="gb-rev-verified" title="Booked with this cleaner">✓ Verified Client</span>
                                <?php endif; ?>
                            </div>
                            <span class="gb-rev-date"><?php echo get_comment_date('M j, Y', $comment); ?></span>
                        </div>
                        <span class="gb-star-row">★★★★★</span>
                        <div class="gb-rev-body">"<?php echo get_comment_text($comment); ?>"</div>
                    </div>
                <?php endforeach; 
                else : ?>
                    <p style="color:#94a3b8; font-style:italic;">No reviews yet. Be the first to book!</p>
                <?php endif; ?>
            </div>

            <div style="margin-top:30px; text-align: left;">
                <button onclick="document.getElementById('gb-review-form-wrap').style.display='block'; this.style.display='none';" class="gb-btn-msg" style="width:auto; display:inline-block; font-size:13px; padding:10px 20px;">+ Write a Review</button>
                
                <div id="gb-review-form-wrap" style="display:none; margin-top:20px; background:#f8fafc; padding:20px; border-radius:12px;">
                    <?php comment_form([
                        'title_reply' => '',
                        'class_submit' => 'gb-btn-book',
                        'label_submit' => 'Post Review',
                        'comment_notes_after' => '',
                        'comment_field' => '<p class="comment-form-comment"><label for="comment">Your Review</label><textarea id="comment" name="comment" cols="45" rows="4" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;"></textarea></p>'
                    ]); ?>
                </div>
            </div>

        </div>

        <div class="gb-profile-sidebar">
            <span class="gb-price-tag">£<?php echo esc_html($rate); ?></span>
            <span class="gb-price-sub">per hour</span>
            
            <a href="<?php echo home_url('/book?cleaner=' . $cleaner_id); ?>" class="gb-btn-book">Book Now</a>
            
            <button onclick="openMsgModal()" class="gb-btn-msg">Message Professional</button>
            
            <div style="font-size:13px; color:#64748b; margin-top:15px; display:flex; align-items:center; justify-content:center; gap:6px;">
                <span class="dashicons dashicons-shield"></span> 100% Satisfaction Guaranteed
            </div>
        </div>

    </div>
</div>

<div id="gb-msg-modal" class="gb-modal">
    <div class="gb-modal-content">
        <span class="gb-close-modal" onclick="closeMsgModal()">&times;</span>
        <h3 style="margin-top:0; color:#0f172a;">Message <?php the_title(); ?></h3>
        <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Ask a question before you book. We'll notify them immediately.</p>
        
        <form id="gb-msg-form">
            <input type="hidden" name="action" value="gb_send_message">
            <input type="hidden" name="cleaner_id" value="<?php echo $cleaner_id; ?>">
            <?php wp_nonce_field('gb_msg_nonce', 'security'); ?>
            
            <div style="display:none;">
                <input type="text" name="gb_honeypot" value="" autocomplete="off">
            </div>

            <label>Your Name</label>
            <input type="text" name="sender_name" required placeholder="e.g. John Smith">
            
            <label>Your Email</label>
            <input type="email" name="sender_email" required placeholder="john@example.com">
            
            <label>Message</label>
            <textarea name="message_body" required placeholder="Hi, do you bring your own vacuum?"></textarea>
            
            <button type="submit" class="gb-btn-book" style="margin-top:10px;">Send Message</button>
        </form>
        <div id="gb-msg-response" style="margin-top:15px; text-align:center; font-weight:600; font-size:14px;"></div>
    </div>
</div>

<script>
    function openMsgModal() { document.getElementById('gb-msg-modal').style.display = 'flex'; }
    function closeMsgModal() { document.getElementById('gb-msg-modal').style.display = 'none'; }
    
    jQuery(document).ready(function($){
        $('#gb-msg-form').on('submit', function(e){
            e.preventDefault();
            var btn = $(this).find('button');
            var resp = $('#gb-msg-response');
            
            btn.text('Sending...').prop('disabled', true);
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                dataType: 'json',
                data: $(this).serialize(),
                success: function(res) {
                    if(res.success) {
                        $('#gb-msg-form').hide();
                        resp.html('<span style="color:#2e7d32;">✅ ' + res.data.message + '</span>');
                        setTimeout(closeMsgModal, 3000);
                    } else {
                        resp.html('<span style="color:#ef4444;">⚠️ ' + res.data.message + '</span>');
                        btn.text('Send Message').prop('disabled', false);
                    }
                },
                error: function() {
                    resp.html('<span style="color:#ef4444;">❌ Server Error. Please try again.</span>');
                    btn.text('Send Message').prop('disabled', false);
                }
            });
        });
    });
</script>

<?php get_footer(); ?>