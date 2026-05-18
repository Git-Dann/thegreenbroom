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
    .gb-date { font-size: 12px; color: #94a3b8; float: right; }
    .gb-email { color: #10b981; font-size: 13px; display: block; margin-bottom: 10px; text-decoration: none; }
    .gb-body { background: #f8fafc; padding: 15px; border-radius: 8px; color: #475569; line-height: 1.5; }
</style>

<div class="gb-inbox-wrap">
    <a href="<?php echo home_url('/cleaner-dashboard'); ?>" style="text-decoration:none; color:#666;">← Back to Dashboard</a>
    <br><br>
    
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
        ?>
            <div class="gb-msg-item">
                <span class="gb-date"><?php echo get_the_date('M j, Y @ g:i a'); ?></span>
                <div class="gb-sender"><?php echo esc_html($sender); ?></div>
                <a href="mailto:<?php echo esc_attr($email); ?>" class="gb-email">Reply to: <?php echo esc_html($email); ?></a>
                <div class="gb-body"><?php echo nl2br(get_the_content()); ?></div>
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
