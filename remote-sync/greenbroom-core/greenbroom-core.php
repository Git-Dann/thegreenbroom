<?php
/**
 * Plugin Name: Green Broom Core
 * Description: Membership, Directory, and Secure Verification system for The Green Broom.
 * Version: 1.0.0
 * Author: The Green Broom
 * Text Domain: greenbroom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'GB_PATH', plugin_dir_path( __FILE__ ) );
define( 'GB_URL', plugin_dir_url( __FILE__ ) );
define( 'GB_VERSION', '1.0.0' );

// Autoload Classes
require_once GB_PATH . 'includes/class-gb-roles.php';
require_once GB_PATH . 'includes/class-gb-cpt.php';
require_once GB_PATH . 'includes/class-gb-uploads.php';

class Green_Broom_Core {

    public function __construct() {
        // Initialize Modules
        new GB_Roles();
        new GB_CPT();
        new GB_Uploads();

        // Enqueue Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
    }

    public function enqueue_frontend() {
        // Geist Font via CDN
        wp_enqueue_style( 'geist-font', 'https://cdn.jsdelivr.net/npm/geist@1.0.0/dist/fonts/geist-sans/style.css', array(), '1.0.0' );
        
        wp_enqueue_style( 'gb-core-style', GB_URL . 'assets/style.css', array(), GB_VERSION );
        wp_enqueue_script( 'gb-core-script', GB_URL . 'assets/script.js', array( 'jquery' ), GB_VERSION, true );
        
        wp_localize_script( 'gb-core-script', 'gb_ajax', array( 
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'gb_search_nonce' )
        ));
    }

    public function enqueue_admin() {
        wp_enqueue_script( 'gb-admin-script', GB_URL . 'assets/script.js', array( 'jquery' ), GB_VERSION, true );
        wp_localize_script( 'gb-admin-script', 'gb_admin_ajax', array( 
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'gb_admin_nonce' )
        ));
    }
}

// Boot the plugin
new Green_Broom_Core();

register_activation_hook( __FILE__, array( 'GB_Roles', 'activate' ) );
register_activation_hook( __FILE__, array( 'GB_Uploads', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GB_Roles', 'deactivate' ) );