<?php
/**
 * Roles and security
 *
 * Extracted verbatim from functions.php, which had grown to 6,642 lines -
 * long enough that a function could be called but never defined without
 * anyone noticing. Nothing here was rewritten in the move.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   1. CORE ROLES & SECURITY
   ========================================================================== */

// Register Custom Roles
function gb_register_roles() {
    add_role( 'gb_manager', 'Manager', array('read'=>true, 'edit_posts'=>true, 'edit_others_posts'=>true, 'upload_files'=>true));
    add_role( 'gb_cleaner', 'Cleaner', array('read'=>true, 'upload_files'=>true, 'edit_posts'=>false));
    add_role( 'gb_customer', 'Customer', array('read'=>true, 'upload_files'=>false, 'edit_posts'=>false));

    // add_role() is a no-op if the role already exists in the DB, so any
    // capability added here later would never reach a site whose roles were
    // created before this line was added. Sync the required caps explicitly.
    // 'edit_others_posts' lets a Manager crop/edit media-library images that
    // were uploaded by someone else (WordPress checks edit_post on the
    // attachment during image editing).
    $required_caps = array(
        'gb_manager'  => array('read', 'edit_posts', 'edit_others_posts', 'upload_files'),
        'gb_cleaner'  => array('read', 'upload_files'),
        'gb_customer' => array('read'),
    );
    foreach ( $required_caps as $role_key => $caps ) {
        $role = get_role( $role_key );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }
    }
}
add_action('init', 'gb_register_roles');

// Hide Admin Bar for Non-Admins
function gb_disable_admin_bar($show) {
    if ( ! current_user_can('administrator') ) {
        return false;
    }
    return $show;
}
add_filter('show_admin_bar', 'gb_disable_admin_bar');

/**
 * Return true for the small set of WordPress media requests that front-end
 * managers and cleaners are allowed to use. The media modal can move between
 * upload.php, attachment edit screens, and the image editor while a single
 * selection is being made. Treating only one of those URLs as media causes an
 * apparent logout when the guard redirects the user back to their dashboard.
 */
function gb_is_allowed_media_admin_request() {
    if (
        ! is_admin()
        || wp_doing_ajax()
        || current_user_can( 'administrator' )
        || ! current_user_can( 'upload_files' )
    ) {
        return false;
    }

    $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    $media_screens = array( 'upload.php', 'media-new.php', 'media.php', 'async-upload.php' );
    if ( in_array( $pagenow, $media_screens, true ) ) {
        return true;
    }

    $target = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
    $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

    // The attachment list is how WordPress opens the gallery browser.
    if ( $pagenow === 'edit.php' && isset( $_REQUEST['post_type'] ) && sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) === 'attachment' ) {
        return true;
    }

    if ( $pagenow !== 'post.php' ) {
        return false;
    }

    // WordPress adds the image-editor flag to the crop/edit URL. Check the
    // flag itself because some hosting/WP combinations do not populate the
    // attachment post type before admin_init runs.
    if ( isset( $_REQUEST['image-editor'] ) || isset( $_REQUEST['image-editor-preview'] ) || $action === 'image-editor' ) {
        return true;
    }

    // Ordinary attachment details/edit screens remain scoped to attachments.
    return $target > 0 && get_post_type( $target ) === 'attachment';
}

/**
 * Keep the auth-cookie policy fresh when WordPress serves a media screen.
 * Media editing can be opened directly from the front-end picker, without a
 * new manager-dashboard page load to refresh the cookie first.
 */
function gb_refresh_media_auth_cookie_policy() {
    if ( ! gb_is_allowed_media_admin_request() || ! is_user_logged_in() ) {
        return;
    }

    $token = wp_get_session_token();
    if ( $token !== '' ) {
        wp_set_auth_cookie( get_current_user_id(), true, is_ssl(), $token );
    }
}
add_action( 'admin_init', 'gb_refresh_media_auth_cookie_policy', 20 );

// Redirect Non-Admins away from /wp-admin/
function gb_security_redirect() {
    // Allow selected unauthenticated admin-post actions.
    if (is_admin() && isset($_REQUEST['action'])) {
        $public_actions = [
            'gb_stripe_webhook',
            'gb_stripe_storefront_checkout',
            'gb_download_resource',
        ];
        if (in_array(sanitize_key(wp_unslash($_REQUEST['action'])), $public_actions, true)) {
            return;
        }
    }

    if ( gb_is_allowed_media_admin_request() ) {
        return;
    }

    if ( is_admin() && ! wp_doing_ajax() && ! current_user_can('administrator') ) {
        $user = wp_get_current_user();
        if ( $user instanceof WP_User && $user->exists() ) {
            wp_redirect( gb_get_post_login_redirect_url( $user ) );
        } else {
            wp_redirect( home_url() );
        }
        exit;
    }
}
add_action('admin_init', 'gb_security_redirect');

// Login Redirects
function gb_custom_login_redirect($redirect_to, $request, $user) {
    if ( $user instanceof WP_User && isset( $user->roles ) && is_array( $user->roles ) ) {
        return gb_get_post_login_redirect_url( $user );
    }

    return $redirect_to;
}
add_filter('login_redirect', 'gb_custom_login_redirect', 10, 3);
