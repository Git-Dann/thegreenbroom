<?php
/*
 * Template Name: Cleaner Manage Listings
 */

if ( ! is_user_logged_in() ) {
    auth_redirect();
}

$current_user = wp_get_current_user();

// --- HANDLE DELETE ACTION ---
if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) ) {
    $post_id = intval($_GET['id']);
    // Security: Check nonce and verify user OWNS this post
    $post_author_id = get_post_field( 'post_author', $post_id );
    
    if ( $post_author_id == $current_user->ID && wp_verify_nonce($_GET['_wpnonce'], 'delete_listing_' . $post_id) ) {
        wp_delete_post($post_id, true); // True = skip trash, force delete
        $msg = '<div class="gb-alert success">Listing deleted successfully.</div>';
    }
}

get_header(); 

// Query the user's listings
$args = array(
    'post_type' => 'gb_listing',
    'post_status' => array('publish', 'draft'), // Show published and drafts
    'author' => $current_user->ID,
    'posts_per_page' => -1
);
$listings = new WP_Query($args);
?>

<div class="gb-dashboard-wrapper">
    <div class="gb-container">

        <div class="listing-header-row">
            <div>
                <a href="/cleaner-dashboard" class="gb-btn-text">&larr; Back to Dashboard</a>
                <h1 class="dash-title">My Listings</h1>
            </div>
            <a href="/edit-listing" class="nav-cta"> + Add New Listing</a>
        </div>

        <?php if ( isset($msg) ) echo $msg; ?>

        <div class="gb-card-table">
            <?php if ( $listings->have_posts() ) : ?>
                <table class="gb-table">
                    <thead>
                        <tr>
                            <th>Listing Title</th>
                            <th>Status</th>
                            <th>Date Posted</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ( $listings->have_posts() ) : $listings->the_post(); 
                            $status = get_post_status();
                            $status_class = ($status === 'publish') ? 'status-active' : 'status-draft';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php the_title(); ?></strong>
                                </td>
                                <td>
                                    <span class="gb-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td><?php echo get_the_date('M j, Y'); ?></td>
                                <td style="text-align: right;">
                                    <a href="/edit-listing?id=<?php the_ID(); ?>" class="gb-btn-small">Edit</a>
                                    
                                    <a href="?action=delete&id=<?php the_ID(); ?>&_wpnonce=<?php echo wp_create_nonce('delete_listing_' . get_the_ID()); ?>" 
                                       class="gb-btn-small delete"
                                       onclick="return confirm('Are you sure you want to delete this listing?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="empty-state">
                    <p>You haven't posted any cleaning listings yet.</p>
                    <a href="/edit-listing" class="gb-btn-outline">Create your first listing</a>
                </div>
            <?php endif; wp_reset_postdata(); ?>
        </div>

    </div>
</div>

<?php get_footer(); ?>