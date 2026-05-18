<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GB_Uploads {

    private $secure_dir;

    public function __construct() {
        $this->secure_dir = self::get_secure_dir();

        add_shortcode( 'gb_cleaner_dashboard', array( $this, 'render_upload_form' ) );
        add_action( 'init', array( $this, 'handle_upload_submission' ) );
        add_action( 'init', array( $this, 'handle_file_proxy' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
    }

    public static function activate() {
        self::ensure_secure_dir();
    }

    private static function get_secure_dir() {
        $upload_dir = wp_upload_dir();

        return trailingslashit( $upload_dir['basedir'] ) . 'gb-secure/';
    }

    private static function get_document_fields() {
        return array(
            'doc_id'      => 'Government ID',
            'doc_address' => 'Proof of Address',
        );
    }

    private static function get_document_meta_key( $field ) {
        return '_gb_' . sanitize_key( (string) $field );
    }

    private static function get_document_label( $field ) {
        $fields = self::get_document_fields();

        return isset( $fields[ $field ] ) ? $fields[ $field ] : ucwords( str_replace( '_', ' ', (string) $field ) );
    }

    private static function normalize_requested_fields( $fields = array() ) {
        $allowed_fields = self::get_document_fields();
        if ( empty( $fields ) ) {
            return array_keys( $allowed_fields );
        }

        $normalized = array();
        foreach ( (array) $fields as $field ) {
            $field = sanitize_key( (string) $field );
            if ( isset( $allowed_fields[ $field ] ) ) {
                $normalized[] = $field;
            }
        }

        return array_values( array_unique( $normalized ) );
    }

    private static function get_cleaner_id_for_user( $user_id ) {
        $user_id = (int) $user_id;
        if ( $user_id < 1 ) {
            return 0;
        }

        $posts = get_posts(
            array(
                'post_type'      => 'gb_cleaner',
                'author'         => $user_id,
                'posts_per_page' => 1,
                'post_status'    => array( 'publish', 'pending', 'draft' ),
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        return ! empty( $posts[0] ) ? (int) $posts[0] : 0;
    }

    public static function ensure_secure_dir() {
        $target = self::get_secure_dir();
        if ( ! file_exists( $target ) ) {
            wp_mkdir_p( $target );
        }

        $htaccess = $target . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all" );
        }

        $index = $target . 'index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }
    }

    public static function user_can_manage_verification_documents() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $user = wp_get_current_user();

        return in_array( 'gb_manager', (array) $user->roles, true );
    }

    public static function current_user_can_view_cleaner_document( $cleaner_id ) {
        $cleaner_id = (int) $cleaner_id;
        if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
            return false;
        }

        if ( self::user_can_manage_verification_documents() ) {
            return true;
        }

        return is_user_logged_in() && (int) get_post_field( 'post_author', $cleaner_id ) === get_current_user_id();
    }

    private static function sync_pending_docs_meta( $cleaner_id ) {
        $cleaner_id = (int) $cleaner_id;
        $author_id = (int) get_post_field( 'post_author', $cleaner_id );
        if ( $author_id < 1 ) {
            return;
        }

        $docs = array();
        foreach ( self::get_document_fields() as $field => $label ) {
            $doc = self::get_cleaner_verification_document( $cleaner_id, $field );
            if ( empty( $doc['status'] ) || $doc['status'] !== 'available' ) {
                continue;
            }

            $docs[] = array(
                'field' => $field,
                'label' => $label,
                'file'  => isset( $doc['file'] ) ? (string) $doc['file'] : '',
                'date'  => isset( $doc['uploaded_at'] ) ? (string) $doc['uploaded_at'] : '',
            );
        }

        if ( empty( $docs ) ) {
            delete_user_meta( $author_id, 'gb_pending_docs' );
            return;
        }

        update_user_meta( $author_id, 'gb_pending_docs', $docs );
    }

    public static function store_cleaner_verification_document( $cleaner_id, $field, $file ) {
        $cleaner_id = (int) $cleaner_id;
        $field = sanitize_key( (string) $field );

        if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
            return new WP_Error( 'gb_invalid_cleaner', 'Invalid cleaner profile.' );
        }

        $document_fields = self::get_document_fields();
        if ( ! isset( $document_fields[ $field ] ) ) {
            return new WP_Error( 'gb_invalid_document', 'Unknown verification document type.' );
        }

        if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
            return new WP_Error( 'gb_missing_upload', 'No file was uploaded.' );
        }

        if ( ! empty( $file['error'] ) ) {
            return new WP_Error( 'gb_upload_error', 'The document upload failed. Please try again.' );
        }

        if ( empty( $file['size'] ) || (int) $file['size'] > ( 8 * MB_IN_BYTES ) ) {
            return new WP_Error( 'gb_upload_size', 'Documents must be smaller than 8MB.' );
        }

        $allowed_mimes = array(
            'pdf'      => 'application/pdf',
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
        );
        $checked_filetype = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'], $allowed_mimes );
        if ( empty( $checked_filetype['ext'] ) || empty( $checked_filetype['type'] ) ) {
            return new WP_Error( 'gb_invalid_type', 'Only PDF, JPG, and PNG files are allowed.' );
        }

        self::ensure_secure_dir();
        self::purge_cleaner_verification_documents( $cleaner_id, array( $field ) );

        $filename = sprintf(
            'cleaner-%d-%s-%s.%s',
            $cleaner_id,
            $field,
            wp_generate_password( 12, false, false ),
            sanitize_file_name( (string) $checked_filetype['ext'] )
        );
        $destination = self::get_secure_dir() . $filename;

        if ( ! @move_uploaded_file( $file['tmp_name'], $destination ) ) {
            return new WP_Error( 'gb_move_failed', 'We could not secure this upload. Please try again.' );
        }

        @chmod( $destination, 0640 );

        $record = array(
            'status'        => 'available',
            'storage'       => 'private',
            'field'         => $field,
            'label'         => $document_fields[ $field ],
            'file'          => $filename,
            'original_name' => sanitize_file_name( (string) $file['name'] ),
            'mime'          => sanitize_text_field( (string) $checked_filetype['type'] ),
            'size'          => (int) $file['size'],
            'uploaded_at'   => current_time( 'mysql' ),
            'uploaded_by'   => get_current_user_id(),
        );

        update_post_meta( $cleaner_id, self::get_document_meta_key( $field ), $record );
        update_post_meta( $cleaner_id, 'is_verified', '0' );

        $author_id = (int) get_post_field( 'post_author', $cleaner_id );
        if ( $author_id > 0 ) {
            update_user_meta( $author_id, 'gb_is_verified', '0' );
            update_user_meta( $author_id, 'gb_verification_status', 'pending' );
        }

        self::sync_pending_docs_meta( $cleaner_id );

        return $record;
    }

    public static function get_cleaner_verification_document( $cleaner_id, $field ) {
        $cleaner_id = (int) $cleaner_id;
        $field = sanitize_key( (string) $field );
        if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
            return array();
        }

        $meta = get_post_meta( $cleaner_id, self::get_document_meta_key( $field ), true );
        if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
            $file = basename( (string) $meta['file'] );
            $path = self::get_secure_dir() . $file;
            if ( file_exists( $path ) ) {
                $meta['status'] = 'available';
                $meta['storage'] = 'private';
                $meta['field'] = $field;
                $meta['label'] = self::get_document_label( $field );

                return $meta;
            }
        }

        if ( is_numeric( $meta ) ) {
            $attachment_id = (int) $meta;
            $filepath = get_attached_file( $attachment_id );
            if ( $filepath && file_exists( $filepath ) ) {
                return array(
                    'status'        => 'available',
                    'storage'       => 'legacy',
                    'field'         => $field,
                    'label'         => self::get_document_label( $field ),
                    'attachment_id' => $attachment_id,
                    'mime'          => get_post_mime_type( $attachment_id ),
                    'file_path'     => $filepath,
                    'file'          => basename( $filepath ),
                    'original_name' => basename( $filepath ),
                    'uploaded_at'   => get_post_field( 'post_date', $attachment_id ),
                );
            }
        }

        return array();
    }

    public static function get_cleaner_verification_documents( $cleaner_id ) {
        $documents = array();
        foreach ( self::get_document_fields() as $field => $label ) {
            $document = self::get_cleaner_verification_document( $cleaner_id, $field );
            if ( ! empty( $document ) ) {
                $documents[ $field ] = $document;
            }
        }

        return $documents;
    }

    public static function get_verification_document_url( $cleaner_id, $field ) {
        $cleaner_id = (int) $cleaner_id;
        $field = sanitize_key( (string) $field );
        if ( $cleaner_id < 1 || ! isset( self::get_document_fields()[ $field ] ) ) {
            return '';
        }

        $url = add_query_arg(
            array(
                'gb_action' => 'view_verification_doc',
                'cleaner'   => $cleaner_id,
                'field'     => $field,
            ),
            home_url( '/' )
        );

        return wp_nonce_url( $url, 'gb_view_verification_doc_' . $cleaner_id . '_' . $field );
    }

    public static function purge_cleaner_verification_documents( $cleaner_id, $fields = array() ) {
        $cleaner_id = (int) $cleaner_id;
        if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
            return;
        }

        foreach ( self::normalize_requested_fields( $fields ) as $field ) {
            $meta_key = self::get_document_meta_key( $field );
            $meta = get_post_meta( $cleaner_id, $meta_key, true );

            if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
                $path = self::get_secure_dir() . basename( (string) $meta['file'] );
                if ( file_exists( $path ) ) {
                    @unlink( $path );
                }
            } elseif ( is_numeric( $meta ) ) {
                wp_delete_attachment( (int) $meta, true );
            }

            delete_post_meta( $cleaner_id, $meta_key );
        }

        self::sync_pending_docs_meta( $cleaner_id );
    }

    public function render_upload_form() {
        $user = wp_get_current_user();
        if ( ! in_array( 'gb_cleaner', (array) $user->roles, true ) ) {
            return '<p>Access denied.</p>';
        }

        $verified = get_user_meta( get_current_user_id(), 'gb_is_verified', true );
        if ( $verified ) {
            return '<p class="gb-success">You are verified.</p>';
        }

        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="gb-form">
            <?php wp_nonce_field( 'gb_upload_doc', 'gb_doc_nonce' ); ?>
            <label>Upload verification document (PDF/JPG/PNG):</label>
            <input type="file" name="gb_document" required accept="image/*,.pdf">
            <button type="submit" class="gb-btn">Upload for Verification</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_upload_submission() {
        if ( ! isset( $_POST['gb_doc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gb_doc_nonce'] ) ), 'gb_upload_doc' ) ) {
            return;
        }

        $cleaner_id = self::get_cleaner_id_for_user( get_current_user_id() );
        if ( $cleaner_id < 1 || empty( $_FILES['gb_document']['name'] ) ) {
            return;
        }

        self::store_cleaner_verification_document( $cleaner_id, 'doc_id', $_FILES['gb_document'] );
    }

    private static function stream_file_response( $filepath, $mime, $filename ) {
        if ( ! file_exists( $filepath ) ) {
            wp_die( 'File not found.' );
        }

        if ( ! $mime && function_exists( 'mime_content_type' ) ) {
            $mime = mime_content_type( $filepath );
        }
        if ( ! $mime ) {
            $mime = 'application/octet-stream';
        }

        $display_name = sanitize_file_name( basename( (string) $filename ) );
        header( 'Content-Type: ' . $mime );
        header( 'Content-Length: ' . filesize( $filepath ) );
        header( 'Content-Disposition: inline; filename="' . $display_name . '"; filename*=UTF-8\'\'' . rawurlencode( $display_name ) );
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $filepath );
        exit;
    }

    public function handle_file_proxy() {
        if ( ! isset( $_GET['gb_action'] ) || $_GET['gb_action'] !== 'view_verification_doc' ) {
            return;
        }

        $cleaner_id = isset( $_GET['cleaner'] ) ? (int) $_GET['cleaner'] : 0;
        $field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
        if (
            $cleaner_id < 1 ||
            ! self::current_user_can_view_cleaner_document( $cleaner_id ) ||
            ! isset( self::get_document_fields()[ $field ] ) ||
            ! isset( $_GET['_wpnonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gb_view_verification_doc_' . $cleaner_id . '_' . $field )
        ) {
            wp_die( 'Unauthorized' );
        }

        $document = self::get_cleaner_verification_document( $cleaner_id, $field );
        if ( empty( $document['status'] ) || $document['status'] !== 'available' ) {
            wp_die( 'File not found.' );
        }

        if ( ! empty( $document['storage'] ) && $document['storage'] === 'legacy' && ! empty( $document['file_path'] ) ) {
            self::stream_file_response( $document['file_path'], isset( $document['mime'] ) ? $document['mime'] : '', isset( $document['original_name'] ) ? $document['original_name'] : basename( $document['file_path'] ) );
        }

        if ( empty( $document['file'] ) ) {
            wp_die( 'File not found.' );
        }

        $filepath = self::get_secure_dir() . basename( (string) $document['file'] );
        self::stream_file_response( $filepath, isset( $document['mime'] ) ? $document['mime'] : '', isset( $document['original_name'] ) ? $document['original_name'] : basename( $filepath ) );
    }

    public function register_admin_page() {
        add_menu_page( 'Verification Queue', 'Verifications', 'manage_options', 'gb-verify', array( $this, 'render_admin_queue' ) );
    }

    public function render_admin_queue() {
        if ( isset( $_GET['action'], $_GET['uid'], $_GET['_wpnonce'] ) ) {
            $action = sanitize_key( wp_unslash( $_GET['action'] ) );
            $uid = (int) $_GET['uid'];
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gb_verify_admin_' . $action . '_' . $uid ) ) {
                $cleaner_id = self::get_cleaner_id_for_user( $uid );
                if ( $action === 'approve' ) {
                    update_user_meta( $uid, 'gb_is_verified', 1 );
                    delete_user_meta( $uid, 'gb_verification_status' );
                    if ( $cleaner_id ) {
                        wp_update_post( array( 'ID' => $cleaner_id, 'post_status' => 'publish' ) );
                        update_post_meta( $cleaner_id, 'is_verified', '1' );
                        self::purge_cleaner_verification_documents( $cleaner_id );
                    }
                } elseif ( $action === 'purge' ) {
                    if ( $cleaner_id ) {
                        self::purge_cleaner_verification_documents( $cleaner_id );
                    }
                    update_user_meta( $uid, 'gb_is_verified', 0 );
                    delete_user_meta( $uid, 'gb_verification_status' );
                    delete_user_meta( $uid, 'gb_pending_docs' );
                }
            }
        }

        $users = get_users( array( 'meta_key' => 'gb_verification_status', 'meta_value' => 'pending' ) );

        echo '<div class="wrap"><h1>Verification Queue</h1>';
        echo '<table class="widefat fixed striped"><thead><tr><th>User</th><th>Documents</th><th>Actions</th></tr></thead><tbody>';

        foreach ( $users as $user ) {
            $cleaner_id = self::get_cleaner_id_for_user( $user->ID );
            $documents = $cleaner_id ? self::get_cleaner_verification_documents( $cleaner_id ) : array();
            $approve_url = wp_nonce_url( admin_url( 'admin.php?page=gb-verify&action=approve&uid=' . $user->ID ), 'gb_verify_admin_approve_' . $user->ID );
            $purge_url = wp_nonce_url( admin_url( 'admin.php?page=gb-verify&action=purge&uid=' . $user->ID ), 'gb_verify_admin_purge_' . $user->ID );

            echo '<tr>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
            echo '<td>';
            if ( ! empty( $documents ) ) {
                foreach ( $documents as $field => $document ) {
                    $url = self::get_verification_document_url( $cleaner_id, $field );
                    echo '<a href="' . esc_url( $url ) . '" target="_blank" class="button">' . esc_html( self::get_document_label( $field ) ) . '</a> ';
                }
            } else {
                echo '<span style="color:#666;">No documents found.</span>';
            }
            echo '</td>';
            echo '<td><a href="' . esc_url( $approve_url ) . '" class="button button-primary">Approve</a> <a href="' . esc_url( $purge_url ) . '" class="button button-link-delete" onclick="return confirm(\'Delete all data?\')">Purge Data</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
