<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class GB_CPT {

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_cleaner_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_and_geocode' ) );
        
        // Admin Columns
        add_filter( 'manage_gb_cleaner_posts_columns', array( $this, 'set_admin_columns' ) );
        add_action( 'manage_gb_cleaner_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );

        // Enqueue Media Uploader for Avatar
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_media' ) );

        // Search Handlers (Unchanged)
        add_shortcode( 'gb_cleaner_search', array( $this, 'render_search_shortcode' ) );
        add_action( 'wp_ajax_gb_cleaner_search', array( $this, 'handle_search' ) );
        add_action( 'wp_ajax_nopriv_gb_cleaner_search', array( $this, 'handle_search' ) );
    }

    public function enqueue_admin_media() {
        if ( get_post_type() === 'gb_cleaner' ) {
            wp_enqueue_media();
        }
    }

    /**
     * 1. Register CPT (Clean Profile Mode)
     * Removed 'editor' and 'thumbnail' from supports to fully control the UI.
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Cleaners',
            'singular_name'      => 'Cleaner',
            'menu_name'          => 'Cleaners',
            'add_new'            => 'Add New Profile',
            'add_new_item'       => 'Add New Cleaner Profile',
            'edit_item'          => 'Edit Profile',
            'view_item'          => 'View Profile',
            'search_items'       => 'Search Cleaners',
            'not_found'          => 'No cleaners found.',
        );

        register_post_type( 'gb_cleaner', array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'supports'           => array( 'title' ), // ONLY Title. No Editor.
            'menu_icon'          => 'dashicons-id-alt',
            'show_in_rest'       => false, // Disable Gutenberg Block Editor
        ));
    }

    /**
     * 2. Meta Boxes: The "Profile" Dashboard
     */
    public function add_cleaner_meta_boxes() {
        add_meta_box(
            'gb_cleaner_profile',      
            'Cleaner Profile Information',         
            array( $this, 'render_meta_box_content' ), 
            'gb_cleaner',              
            'normal',                  
            'high'                     
        );
    }

    public function render_meta_box_content( $post ) {
        // Retrieve Data
        $rate     = get_post_meta( $post->ID, 'gb_hourly_rate', true );
        $postcode = get_post_meta( $post->ID, 'gb_postcode', true );
        $radius   = get_post_meta( $post->ID, 'gb_radius', true );
        $verified = get_post_meta( $post->ID, 'gb_verified', true );
        
        // New Fields
        $avatar_id  = get_post_meta( $post->ID, 'gb_avatar_id', true );
        $avatar_url = $avatar_id ? wp_get_attachment_image_src( $avatar_id, 'thumbnail' )[0] : '';
        $bio        = get_post_meta( $post->ID, 'gb_bio', true );
        $experience = get_post_meta( $post->ID, 'gb_experience', true );
        $services   = get_post_meta( $post->ID, 'gb_services', true ) ?: array();

        $available_services = ['Standard Clean', 'Deep Clean', 'End of Tenancy', 'Ironing', 'Oven Cleaning'];

        wp_nonce_field( 'gb_save_cleaner_details', 'gb_cleaner_nonce' );
        ?>
        <style>
            .gb-profile-grid { display: grid; grid-template-columns: 250px 1fr; gap: 30px; }
            .gb-avatar-section { text-align: center; background: #f9f9f9; padding: 20px; border: 1px dashed #ccc; border-radius: 8px; }
            .gb-avatar-preview { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; background: #eee; display: block; margin: 0 auto 15px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .gb-fields { display: flex; flex-direction: column; gap: 15px; }
            .gb-field-row { display: grid; grid-template-columns: 150px 1fr; align-items: start; }
            .gb-field-row label { font-weight: 600; padding-top: 5px; }
            .gb-field-row input[type="text"], .gb-field-row input[type="number"], .gb-field-row textarea { width: 100%; max-width: 400px; padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
            .gb-field-row textarea { height: 100px; }
            .gb-services-list label { display: inline-block; margin-right: 15px; font-weight: normal; margin-bottom: 5px; }
            .gb-verified-toggle { padding: 10px; background: #e6ffed; border: 1px solid #b6e2bd; border-radius: 4px; display: inline-block; }
        </style>

        <div class="gb-profile-grid">
            
            <div class="gb-avatar-section">
                <img src="<?php echo $avatar_url ? esc_url($avatar_url) : 'https://www.gravatar.com/avatar/?d=mp&s=150'; ?>" class="gb-avatar-preview" id="gb-avatar-preview">
                <input type="hidden" name="gb_avatar_id" id="gb-avatar-id" value="<?php echo esc_attr($avatar_id); ?>">
                <button type="button" class="button" id="gb-upload-avatar">Upload Photo</button>
                <p class="description" style="margin-top:10px;">Recommended: 500x500px</p>
                <?php if($avatar_id): ?>
                    <br><a href="#" id="gb-remove-avatar" style="color:red; font-size:12px; text-decoration:none;">Remove Photo</a>
                <?php endif; ?>
            </div>

            <div class="gb-fields">
                
                <div class="gb-field-row">
                    <label>Status:</label>
                    <div>
                        <div class="gb-verified-toggle">
                            <input type="checkbox" id="gb_verified" name="gb_verified" value="1" <?php checked( $verified, 1 ); ?>>
                            <label for="gb_verified"><strong>Verified Cleaner</strong> (ID Checked)</label>
                        </div>
                    </div>
                </div>

                <div class="gb-field-row">
                    <label>About Me:</label>
                    <textarea name="gb_bio" placeholder="Tell clients about your experience and style..."><?php echo esc_textarea($bio); ?></textarea>
                </div>

                <div class="gb-field-row">
                    <label>Experience:</label>
                    <input type="number" name="gb_experience" value="<?php echo esc_attr($experience); ?>" placeholder="e.g. 5"> <span style="line-height:30px; margin-left:5px;">Years</span>
                </div>

                <div class="gb-field-row">
                    <label>Services:</label>
                    <div class="gb-services-list">
                        <?php foreach($available_services as $service): ?>
                            <label>
                                <input type="checkbox" name="gb_services[]" value="<?php echo esc_attr($service); ?>" <?php echo (in_array($service, $services)) ? 'checked' : ''; ?>> 
                                <?php echo esc_html($service); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr style="border:0; border-top:1px solid #eee; width:100%;">

                <div class="gb-field-row">
                    <label>Hourly Rate (£):</label>
                    <input type="number" name="gb_hourly_rate" value="<?php echo esc_attr($rate); ?>" step="0.50" placeholder="15.00">
                </div>

                <div class="gb-field-row">
                    <label>Postcode:</label>
                    <div>
                        <input type="text" name="gb_postcode" value="<?php echo esc_attr($postcode); ?>" placeholder="SW1A 1AA">
                        <p class="description">Used for search location.</p>
                    </div>
                </div>

                <div class="gb-field-row">
                    <label>Service Radius:</label>
                    <div>
                        <input type="number" name="gb_radius" value="<?php echo esc_attr($radius); ?>" placeholder="10"> <span style="line-height:30px; margin-left:5px;">Miles</span>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    public function save_meta_and_geocode( $post_id ) {
        if ( ! isset( $_POST['gb_cleaner_nonce'] ) || ! wp_verify_nonce( $_POST['gb_cleaner_nonce'], 'gb_save_cleaner_details' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Save Fields
        if(isset($_POST['gb_hourly_rate'])) update_post_meta($post_id, 'gb_hourly_rate', sanitize_text_field($_POST['gb_hourly_rate']));
        if(isset($_POST['gb_radius'])) update_post_meta($post_id, 'gb_radius', intval($_POST['gb_radius']));
        if(isset($_POST['gb_bio'])) update_post_meta($post_id, 'gb_bio', sanitize_textarea_field($_POST['gb_bio']));
        if(isset($_POST['gb_experience'])) update_post_meta($post_id, 'gb_experience', intval($_POST['gb_experience']));
        
        // Avatar
        if(isset($_POST['gb_avatar_id'])) update_post_meta($post_id, 'gb_avatar_id', intval($_POST['gb_avatar_id']));

        // Services (Array)
        $services = isset($_POST['gb_services']) ? array_map('sanitize_text_field', $_POST['gb_services']) : array();
        update_post_meta($post_id, 'gb_services', $services);

        // Verified
        $verified = isset($_POST['gb_verified']) ? 1 : 0;
        update_post_meta($post_id, 'gb_verified', $verified);

        // Geocoding Logic
        if ( isset( $_POST['gb_postcode'] ) ) {
            $old_postcode = get_post_meta( $post_id, 'gb_postcode', true );
            $new_postcode = sanitize_text_field( $_POST['gb_postcode'] );
            update_post_meta( $post_id, 'gb_postcode', $new_postcode );

            if ( $new_postcode !== $old_postcode || ! get_post_meta( $post_id, 'gb_lat', true ) ) {
                $response = wp_remote_get( 'https://api.postcodes.io/postcodes/' . $new_postcode );
                if ( ! is_wp_error( $response ) ) {
                    $body = json_decode( wp_remote_retrieve_body( $response ), true );
                    if ( isset( $body['result'] ) ) {
                        update_post_meta( $post_id, 'gb_lat', $body['result']['latitude'] );
                        update_post_meta( $post_id, 'gb_lng', $body['result']['longitude'] );
                    }
                }
            }
        }
    }

    /**
     * 3. Admin Columns (With Avatar!)
     */
    public function set_admin_columns( $columns ) {
        return array(
            'cb' => $columns['cb'],
            'gb_avatar' => '', // Icon column
            'title' => 'Name',
            'gb_location' => 'Location',
            'gb_rate' => 'Rate',
            'gb_verified' => 'Status',
            'date' => 'Date'
        );
    }

    public function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'gb_avatar':
                $img_id = get_post_meta( $post_id, 'gb_avatar_id', true );
                if($img_id) {
                    echo wp_get_attachment_image( $img_id, array(40,40), false, array('style' => 'border-radius:50%; width:40px; height:40px; object-fit:cover;') );
                } else {
                    echo '<div style="width:40px;height:40px;background:#eee;border-radius:50%;"></div>';
                }
                break;
            case 'gb_location':
                $pc = get_post_meta( $post_id, 'gb_postcode', true );
                echo $pc ? '<strong>' . esc_html( strtoupper($pc) ) . '</strong>' : '—';
                break;
            case 'gb_rate':
                $rate = get_post_meta( $post_id, 'gb_hourly_rate', true );
                echo $rate ? '£' . esc_html( $rate ) : '—';
                break;
            case 'gb_verified':
                $verified = get_post_meta( $post_id, 'gb_verified', true );
                echo $verified ? '<span class="dashicons dashicons-yes-alt" style="color:green;"></span>' : '<span class="dashicons dashicons-minus" style="color:#ccc;"></span>';
                break;
        }
    }

    /** Search Logic (Keep existing handle_search & shortcode methods here) */
    public function render_search_shortcode() { /* ... keep previous code ... */ return ''; }
    public function handle_search() { /* ... keep previous code ... */ }
}