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
        $rate     = get_post_meta( $post->ID, 'hourly_rate', true );
        if ( $rate === '' || $rate === null ) { $rate = get_post_meta( $post->ID, 'gb_hourly_rate', true ); }
        $postcode = get_post_meta( $post->ID, 'gb_postcode', true );
        $radius   = get_post_meta( $post->ID, 'gb_radius', true );
        $verified = get_post_meta( $post->ID, 'gb_verified', true );
        
        // New Fields
        $avatar_id  = get_post_meta( $post->ID, 'gb_avatar_id', true );
        $avatar_url = $avatar_id ? wp_get_attachment_image_src( $avatar_id, 'thumbnail' )[0] : '';
        $bio        = get_post_meta( $post->ID, 'gb_bio', true );
        $experience = get_post_meta( $post->ID, 'gb_experience', true );
        $services   = get_post_meta( $post->ID, 'gb_services', true ) ?: array();

        /*
         * Read the real Services catalogue rather than a list kept here.
         *
         * This was hard-coded and had gone stale: it offered "Standard Clean",
         * which no longer exists (the site calls it Regular Clean), and omitted
         * every service actually published. Since the listings filter matches
         * these strings against gb_service post titles, a cleaner ticked here
         * could never be found by that filter. Falls back to the old list only
         * if no services are published, so the box is never empty.
         */
        $gb_service_posts = get_posts( array(
            'post_type'      => 'gb_service',
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        $available_services = wp_list_pluck( $gb_service_posts, 'post_title' );
        if ( empty( $available_services ) ) {
            $available_services = ['Regular Clean', 'Deep Clean', 'End of Tenancy', 'Ironing', 'Oven Cleaning'];
        }

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
        if(isset($_POST['gb_hourly_rate'])) update_post_meta($post_id, 'hourly_rate', sanitize_text_field($_POST['gb_hourly_rate']));
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
        update_post_meta( $post_id, '_gb_location', $new_postcode );
        update_post_meta( $post_id, 'location', $new_postcode );

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

    public function render_search_shortcode() {
        ob_start();
        ?>
        <form id="gb-search-form">
            <input type="text" name="postcode" placeholder="Your postcode (e.g. SW1A 1AA)" required>
            <select name="radius">
                <option value="5">Within 5 miles</option>
                <option value="10" selected>Within 10 miles</option>
                <option value="20">Within 20 miles</option>
                <option value="50">Within 50 miles</option>
            </select>
            <input type="number" name="max_rate" placeholder="Max hourly rate (£)" min="1" step="0.50">
            <button type="submit">Search</button>
        </form>
        <div id="gb-results-grid"></div>
        <?php
        return ob_get_clean();
    }

    public function handle_search() {
        check_ajax_referer( 'gb_search_nonce', 'nonce' );

        $postcode = sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) );
        $radius   = absint( $_POST['radius'] ?? 10 );
        $max_rate = floatval( $_POST['max_rate'] ?? 0 );

        if ( empty( $postcode ) ) {
            wp_send_json_error( 'Please enter a postcode.' );
        }

        $response = wp_remote_get( 'https://api.postcodes.io/postcodes/' . rawurlencode( $postcode ) );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Could not look up that postcode. Please try again.' );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['result'] ) ) {
            wp_send_json_error( 'Postcode not found. Please check and try again.' );
        }

        $search_lat = (float) $body['result']['latitude'];
        $search_lng = (float) $body['result']['longitude'];

        $cleaners = get_posts( array(
            'post_type'   => 'gb_cleaner',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => array(
                array( 'key' => 'gb_lat', 'compare' => 'EXISTS' ),
                array( 'key' => 'gb_lng', 'compare' => 'EXISTS' ),
            ),
        ) );

        $results = array();
        foreach ( $cleaners as $cleaner ) {
            $lat            = (float) get_post_meta( $cleaner->ID, 'gb_lat', true );
            $lng            = (float) get_post_meta( $cleaner->ID, 'gb_lng', true );
            $rate           = (float) get_post_meta( $cleaner->ID, 'gb_hourly_rate', true );
            $cleaner_radius = (int)   get_post_meta( $cleaner->ID, 'gb_radius', true );
            $distance       = $this->haversine_miles( $search_lat, $search_lng, $lat, $lng );

            if ( $cleaner_radius > 0 && $distance > $cleaner_radius ) continue;
            if ( $distance > $radius ) continue;
            if ( $max_rate > 0 && $rate > $max_rate ) continue;

            $results[] = array( 'post' => $cleaner, 'distance' => $distance, 'rate' => $rate );
        }

        if ( empty( $results ) ) {
            wp_send_json_error( 'No cleaners found in your area. Try increasing the search radius.' );
        }

        usort( $results, function( $a, $b ) { return $a['distance'] <=> $b['distance']; } );

        ob_start();
        foreach ( $results as $result ) {
            $post      = $result['post'];
            $avatar_id = get_post_meta( $post->ID, 'gb_avatar_id', true );
            $avatar    = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
            $verified  = get_post_meta( $post->ID, 'gb_verified', true );
            $bio       = get_post_meta( $post->ID, 'gb_bio', true );
            ?>
            <div class="gb-cleaner-card">
                <?php if ( $avatar ) : ?>
                    <img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>">
                <?php endif; ?>
                <h3><a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></h3>
                <?php if ( $result['rate'] ) : ?>
                    <p>£<?php echo esc_html( $result['rate'] ); ?>/hr</p>
                <?php endif; ?>
                <p><?php echo esc_html( round( $result['distance'], 1 ) ); ?> miles away</p>
                <?php if ( $bio ) : ?>
                    <p><?php echo esc_html( wp_trim_words( $bio, 15 ) ); ?></p>
                <?php endif; ?>
                <?php if ( $verified ) : ?><span>&#10003; Verified</span><?php endif; ?>
                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">View Profile</a>
            </div>
            <?php
        }
        wp_send_json_success( ob_get_clean() );
    }

    private function haversine_miles( $lat1, $lng1, $lat2, $lng2 ) {
        $dlat = deg2rad( $lat2 - $lat1 );
        $dlng = deg2rad( $lng2 - $lng1 );
        $a    = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
        return 3958.8 * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
    }
}
