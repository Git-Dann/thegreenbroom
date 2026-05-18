<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class GB_Roles {

    public function __construct() {
        add_action( 'init', array( $this, 'register_roles' ) );
        
        // Admin Columns
        add_filter( 'manage_users_columns', array( $this, 'add_sub_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'show_sub_column_content' ), 10, 3 );
        
        // AJAX Toggle
        add_action( 'wp_ajax_gb_toggle_sub', array( $this, 'ajax_toggle_subscription' ) );

        // Cron
        add_action( 'gb_daily_expiry_check', array( $this, 'check_expiries' ) );
    }

    public static function activate() {
        // Schedule Cron
        if ( ! wp_next_scheduled( 'gb_daily_expiry_check' ) ) {
            wp_schedule_event( time(), 'daily', 'gb_daily_expiry_check' );
        }
    }

    public function register_roles() {
        add_role( 'gb_client', 'Client', array( 'read' => true ) );
        add_role( 'gb_cleaner', 'Cleaner', array( 'read' => true, 'upload_files' => true ) );
    }

    /**
     * Gating Logic: Can user view contacts?
     */
    public static function can_view_contact_details( $user_id ) {
        if ( user_can( $user_id, 'administrator' ) ) return true;
        
        $status = get_user_meta( $user_id, 'gb_subscription_status', true );
        return $status === 'active';
    }

    // --- Admin UI --- //

    public function add_sub_column( $columns ) {
        $columns['gb_sub_status'] = 'Subscription';
        return $columns;
    }

    public function show_sub_column_content( $value, $column_name, $user_id ) {
        if ( 'gb_sub_status' === $column_name ) {
            $status = get_user_meta( $user_id, 'gb_subscription_status', true ) ?: 'inactive';
            $color = ( $status === 'active' ) ? 'green' : 'red';
            $btn_text = ( $status === 'active' ) ? 'Deactivate' : 'Activate';
            
            return sprintf(
                '<span style="color:%s; font-weight:bold;">%s</span><br><button class="button button-small gb-toggle-sub" data-uid="%d" data-status="%s">%s</button>',
                $color, ucfirst($status), $user_id, $status, $btn_text
            );
        }
        return $value;
    }

    public function ajax_toggle_subscription() {
        check_ajax_referer( 'gb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_users' ) ) wp_send_json_error();

        $user_id = intval( $_POST['user_id'] );
        $current_status = sanitize_text_field( $_POST['status'] );
        $new_status = ( $current_status === 'active' ) ? 'inactive' : 'active';

        update_user_meta( $user_id, 'gb_subscription_status', $new_status );
        
        // If activating, set expiry to +30 days (example)
        if ( $new_status === 'active' ) {
            update_user_meta( $user_id, 'gb_subscription_expiry', date( 'Y-m-d', strtotime( '+30 days' ) ) );
        }

        wp_send_json_success( array( 'new_status' => $new_status ) );
    }

    // --- Cron Logic --- //

    public function check_expiries() {
        $args = array(
            'meta_key' => 'gb_subscription_status',
            'meta_value' => 'active'
        );
        $users = get_users( $args );

        foreach ( $users as $user ) {
            $expiry = get_user_meta( $user->ID, 'gb_subscription_expiry', true );
            if ( $expiry && strtotime( $expiry ) < time() ) {
                // Expire User
                update_user_meta( $user->ID, 'gb_subscription_status', 'inactive' );
                
                // Fire Hook for Email
                do_action( 'gb_subscription_expired', $user->ID );
            }
        }
    }
}