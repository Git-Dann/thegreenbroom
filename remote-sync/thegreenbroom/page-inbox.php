<?php
/* Template Name: Inbox */

if (!is_user_logged_in()) { wp_redirect(home_url('/login')); exit; }
$current_user = wp_get_current_user();

// 1. Find Linked Profile
$cleaner_query = new WP_Query([
    'post_type' => 'gb_cleaner',
    'author' => $current_user->ID,
    'posts_per_page' => 1,
    'post_status' => ['publish', 'pending', 'draft'],
]);
$cleaner_id = ($cleaner_query->have_posts()) ? $cleaner_query->posts[0]->ID : 0;

get_header(); 
?>

<style>
    .gb-inbox-wrap { max-width: 900px; margin: 60px auto; padding: 20px; font-family: sans-serif; }
    .gb-msg-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
    .gb-msg-item { padding: 25px; border-bottom: 1px solid #f1f5f9; }
    .gb-msg-item:last-child { border-bottom: none; }
    .gb-sender { font-weight: 700; color: #0f172a; font-size: 16px; }
    .gb-date { font-size: 12px; color: #64748b; float: right; }
    .gb-email { color: #10b981; font-size: 13px; display: block; margin-bottom: 10px; text-decoration: none; }
    .gb-body { background: #f8fafc; padding: 15px; border-radius: 8px; color: #475569; line-height: 1.5; }
</style>

<div class="gb-inbox-wrap">
    <a href="<?php echo home_url('/cleaner-dashboard'); ?>" style="text-decoration:none; color:#666;">← Back to Dashboard</a>
    <br><br>

    <?php
    $inbox_notice = isset($_GET['msg']) ? sanitize_key(wp_unslash($_GET['msg'])) : '';
    if ($inbox_notice === 'reply_sent'): ?>
        <div style="background:#f0fdf4; border:1px solid #dcfce7; color:#166534; padding:14px 16px; border-radius:8px; margin-bottom:16px;">✅ Reply sent and marked as replied.</div>
    <?php elseif ($inbox_notice === 'reply_expired'): ?>
        <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:14px 16px; border-radius:8px; margin-bottom:16px;">That form had timed out, so nothing was sent. Your reply is back in its box below: press Send again.</div>
    <?php elseif ($inbox_notice === 'reply_queued'): ?>
        <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:14px 16px; border-radius:8px; margin-bottom:16px;">Reply saved and marked as replied. The email service did not take it just now, so it is queued and will be retried automatically. There is no need to send it again.</div>
    <?php elseif ($inbox_notice === 'replied_nomail'): ?>
        <div style="background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:14px 16px; border-radius:8px; margin-bottom:16px;">Marked as replied, but the email could not be sent (no valid sender email, or mail is not configured yet).</div>
    <?php elseif ($inbox_notice === 'reply_error'): ?>
        <div style="background:#fef2f2; border:1px solid #fee2e2; color:#991b1b; padding:14px 16px; border-radius:8px; margin-bottom:16px;">Could not send that reply. Please add a message and try again.</div>
    <?php endif; ?>

    <div class="gb-msg-card">
        <div style="padding:20px; border-bottom:1px solid #eee; background:#fafafa; font-weight:bold;">Inbox</div>
        
        <?php 
        if ($cleaner_id) {
            $msgs = new WP_Query([
                'post_type' => 'gb_message',
                'meta_key' => '_gb_related_cleaner',
                'meta_value' => $cleaner_id,
                'posts_per_page' => 20
            ]);
            
            if($msgs->have_posts()):
                while($msgs->have_posts()): $msgs->the_post(); 
                $sender = get_post_meta(get_the_ID(), '_gb_sender_name', true);
                $email = get_post_meta(get_the_ID(), '_gb_sender_email', true);
                $responded = get_post_meta(get_the_ID(), '_gb_responded_date', true);
        ?>
            <div class="gb-msg-item">
                <span class="gb-date"><?php echo get_the_date('M j, Y @ g:i a'); ?></span>
                <div class="gb-sender">
                    <?php echo esc_html($sender); ?>
                    <?php if ($responded): ?>
                        <span style="font-size:11px; font-weight:700; color:#166534; background:#dcfce7; padding:2px 8px; border-radius:10px; margin-left:8px;">Replied</span>
                    <?php else: ?>
                        <span style="font-size:11px; font-weight:700; color:#9a3412; background:#fff7ed; padding:2px 8px; border-radius:10px; margin-left:8px;">Awaiting reply</span>
                    <?php endif; ?>
                </div>
                <?php /* Was a mailto link. Replying outside the platform records
                         nothing, so the thread sat on "Awaiting reply" forever and
                         the customer's reply never came back here. Show the address
                         as plain text; the form below is the way to answer. */ ?>
                <span class="gb-email">From: <?php echo esc_html($email); ?></span>
                <div class="gb-body"><?php echo nl2br(esc_html(get_the_content())); ?></div>
                <?php if (is_email($email)): ?>
                <form method="post" action="<?php echo esc_url(home_url('/inbox')); ?>" style="margin-top:12px;">
                    <input type="hidden" name="gb_reply_action" value="reply_message">
                    <input type="hidden" name="message_id" value="<?php echo get_the_ID(); ?>">
                    <input type="hidden" name="gb_reply_redirect" value="<?php echo esc_url(home_url('/inbox')); ?>">
                    <?php wp_nonce_field('gb_reply_message', 'gb_reply_nonce'); ?>
                    <textarea name="gb_reply_body" required placeholder="Type your reply&hellip;" style="width:100%; min-height:90px; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:14px; box-sizing:border-box; line-height:1.5;"><?php echo esc_textarea(function_exists('gb_take_reply_draft') ? gb_take_reply_draft(get_the_ID()) : ''); ?></textarea>
                    <button type="submit" style="margin-top:8px; background:#2e7d32; color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer;">Send Reply</button>
                </form>
                <?php else: ?>
                    <p style="margin:12px 0 0; padding:10px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; font-size:13px;">
                        No valid reply address was supplied with this enquiry, so it cannot be answered from here.
                    </p>
                <?php endif; ?>
            </div>
        <?php endwhile; wp_reset_postdata();
        else: ?>
            <div style="padding:50px; text-align:center; color:#999;">No messages found.</div>
        <?php endif; 
        } else { echo "<div style='padding:20px;'>Error: No profile linked.</div>"; }
        ?>
    </div>
</div>
<?php get_footer(); ?>
