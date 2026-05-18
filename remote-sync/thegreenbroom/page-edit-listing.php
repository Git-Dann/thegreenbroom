<?php
/*
 * Template Name: Cleaner Edit Listing
 */

if ( ! is_user_logged_in() ) auth_redirect();

$current_user = wp_get_current_user();
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$listing = null;
$message = '';

// --- SECURITY: Verify Ownership if Editing ---
if ( $post_id > 0 ) {
    $listing = get_post($post_id);
    if ( $listing->post_author != $current_user->ID ) {
        wp_die("You are not authorized to edit this listing.");
    }
}

// --- HANDLE FORM SUBMISSION ---
if ( isset($_POST['gb_save_listing']) && wp_verify_nonce($_POST['gb_listing_nonce'], 'save_listing_action') ) {
    
    $title = sanitize_text_field($_POST['listing_title']);
    $content = wp_kses_post($_POST['listing_desc']);
    $price = sanitize_text_field($_POST['listing_price']); // Custom Field
    
    $post_data = array(
        'ID'           => $post_id, // If 0, it inserts new; if ID exists, it updates
        'post_title'   => $title,
        'post_content' => $content,
        'post_type'    => 'gb_listing',
        'post_status'  => 'publish',
        'post_author'  => $current_user->ID,
    );

    // Save/Update Post
    if ( $post_id === 0 ) {
        $post_id = wp_insert_post($post_data); // Create New
    } else {
        wp_update_post($post_data); // Update Existing
    }

    // Save Custom Fields (Price, Location, etc.)
    update_post_meta($post_id, 'gb_price', $price);

    $message = '<div class="gb-alert success">Listing saved successfully! <a href="/manage-listings">Back to list</a></div>';
    
    // Reload listing object to show new values
    $listing = get_post($post_id);
}

get_header(); 
?>

<div class="gb-dashboard-wrapper">
    <div class="gb-container">
        
        <div style="max-width: 800px; margin: 0 auto;">
            <a href="/manage-listings" class="gb-btn-text">&larr; Back to My Listings</a>
            
            <h1 class="dash-title" style="margin-top: 20px;">
                <?php echo ($listing) ? 'Edit Listing' : 'Create New Listing'; ?>
            </h1>
            
            <?php echo $message; ?>

            <form method="post" class="gb-form-card">
                <?php wp_nonce_field('save_listing_action', 'gb_listing_nonce'); ?>

                <div class="form-group">
                    <label>Listing Title</label>
                    <input type="text" name="listing_title" class="gb-input" 
                           placeholder="e.g. 3-Bedroom Deep Clean in Manchester"
                           value="<?php echo ($listing) ? esc_attr($listing->post_title) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Price (GBP)</label>
                    <input type="number" name="listing_price" class="gb-input" 
                           placeholder="e.g. 50"
                           value="<?php echo ($listing) ? esc_attr(get_post_meta($listing->ID, 'gb_price', true)) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <?php 
                    $content = ($listing) ? $listing->post_content : '';
                    wp_editor($content, 'listing_desc', array(
                        'media_buttons' => false,
                        'textarea_rows' => 8,
                        'editor_class' => 'gb-input'
                    )); 
                    ?>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button type="submit" name="gb_save_listing" class="nav-cta" style="border:none; cursor:pointer;">
                        <?php echo ($listing) ? 'Update Listing' : 'Publish Listing'; ?>
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>

<?php get_footer(); ?>