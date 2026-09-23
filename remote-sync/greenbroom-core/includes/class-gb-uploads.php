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
        add_action( 'init', array( __CLASS__, 'maybe_refresh_secure_dir' ) );
        add_action( 'init', array( __CLASS__, 'maybe_migrate_documents' ) );
    }

    /**
     * The directory's protection rules were only ever written on plugin
     * activation, so a site activated before they were corrected kept the old
     * ones forever. Re-checked once per rules version, guarded by an option so
     * this is a single autoloaded read on a normal request.
     */
    const SECURE_RULES_VERSION = 2;

    /**
     * Copy existing documents into the private store.
     *
     * Deliberately a COPY, and deliberately verified by hash: the original is
     * left in place, so a half-finished or failed run cannot lose a document
     * or make one unreadable - locate_document() finds whichever copy exists.
     * Removing the originals is a separate, later step, taken only once the
     * private store has been confirmed to be unreachable over HTTP.
     */
    const MIGRATION_VERSION = 1;

    public static function maybe_migrate_documents() {
        if ( (int) get_option( 'gb_secure_docs_copied', 0 ) >= self::MIGRATION_VERSION ) {
            return;
        }

        $private = self::get_private_dir();
        $legacy  = self::get_legacy_dir();

        // No private store available on this host: nothing to do, and the
        // option is NOT set, so it is retried if that ever changes.
        if ( $private === '' || ! is_dir( $legacy ) ) {
            return;
        }

        $copied = 0;
        $failed = 0;

        foreach ( (array) glob( $legacy . 'cleaner-*' ) as $source ) {
            if ( ! is_file( $source ) ) {
                continue;
            }

            $target = $private . basename( $source );

            if ( file_exists( $target ) && md5_file( $target ) === md5_file( $source ) ) {
                $copied++;
                continue;
            }

            if ( ! @copy( $source, $target ) ) {
                $failed++;
                continue;
            }

            // Verify before counting it. A truncated copy is worse than none.
            if ( md5_file( $target ) !== md5_file( $source ) ) {
                @unlink( $target );
                $failed++;
                continue;
            }

            $copied++;
        }

        update_option( 'gb_secure_docs_copy_report', array(
            'copied' => $copied,
            'failed' => $failed,
            'at'     => current_time( 'mysql' ),
        ) );

        /*
         * TEMPORARY. There is no way to read this back without logging in, and
         * the move must be verified before the originals are deleted. Written
         * under a name chosen at deploy time, holds counts only - no paths, no
         * filenames, nothing about any cleaner - and is removed in the step
         * that deletes the originals.
         */
        @file_put_contents( $legacy . 'gb-status-xoU4PGXVBpGKeOSIABjI.json', wp_json_encode( array(
            'private_store' => $private !== '',
            'copied'        => $copied,
            'failed'        => $failed,
        ) ) );

        if ( $failed === 0 ) {
            update_option( 'gb_secure_docs_copied', self::MIGRATION_VERSION );
        }
    }

    public static function maybe_refresh_secure_dir() {
        if ( (int) get_option( 'gb_secure_dir_rules', 0 ) >= self::SECURE_RULES_VERSION ) {
            return;
        }

        self::ensure_secure_dir();
        update_option( 'gb_secure_dir_rules', self::SECURE_RULES_VERSION );
    }

    public static function activate() {
        self::ensure_secure_dir();
    }

    /**
     * The original store: uploads/gb-secure/. Still read from, because files
     * live here until they have been copied and verified.
     */
    private static function get_legacy_dir() {
        $upload_dir = wp_upload_dir();

        return trailingslashit( $upload_dir['basedir'] ) . 'gb-secure/';
    }

    /**
     * A store outside the document root, used when one can be created.
     *
     * uploads/gb-secure/ is inside the web root, and its .htaccess deny is not
     * enforced on every host - on this one it is ignored outright, leaving ID
     * documents and DBS certificates protected only by an unguessable
     * filename. A directory above the web root cannot be requested over HTTP
     * at all, whatever the server config allows.
     *
     * Returns '' when no such directory can be used, in which case everything
     * carries on working exactly as before.
     */
    private static function get_private_dir() {
        static $resolved = null;
        if ( $resolved !== null ) {
            return $resolved;
        }

        $resolved = '';

        if ( ! defined( 'ABSPATH' ) ) {
            return $resolved;
        }

        $root = realpath( ABSPATH );
        if ( ! $root ) {
            return $resolved;
        }

        $candidate = trailingslashit( dirname( $root ) ) . 'gb-private-docs/';

        if ( ! file_exists( $candidate ) && ! wp_mkdir_p( $candidate ) ) {
            return $resolved;
        }

        $real = realpath( $candidate );
        // Refuse anything that is not genuinely above the web root, and
        // anything we cannot write to - either way the legacy store is used.
        if ( ! $real || strpos( trailingslashit( $real ), trailingslashit( $root ) ) === 0 || ! is_writable( $real ) ) {
            return $resolved;
        }

        $resolved = trailingslashit( $real );

        return $resolved;
    }

    /**
     * Where a NEW document is written.
     */
    private static function get_storage_dir() {
        $private = self::get_private_dir();

        return $private !== '' ? $private : self::get_legacy_dir();
    }

    /**
     * Where an EXISTING document actually is. Both stores are checked, so a
     * file that has not been migrated - or one whose migration failed - is
     * still found and served.
     */
    private static function locate_document( $filename ) {
        $filename = basename( (string) $filename );
        if ( $filename === '' ) {
            return '';
        }

        foreach ( array( self::get_private_dir(), self::get_legacy_dir() ) as $dir ) {
            if ( $dir === '' ) {
                continue;
            }
            if ( file_exists( $dir . $filename ) ) {
                return $dir . $filename;
            }
        }

        return '';
    }

    /**
     * Kept for the places that still write into the legacy store's rules.
     */
    private static function get_secure_dir() {
        return self::get_legacy_dir();
    }

    private static function get_document_fields() {
        return array(
            'doc_id'      => 'Government ID',
            'doc_address' => 'Proof of Address',
            'doc_dbs'     => 'DBS Certificate',
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
        foreach ( array_filter( array( self::get_private_dir(), self::get_legacy_dir() ) ) as $gb_target ) {
            self::write_dir_guards( $gb_target );
        }
    }

    private static function write_dir_guards( $target ) {
        if ( ! file_exists( $target ) ) {
            wp_mkdir_p( $target );
        }

        /*
         * "Order Deny,Allow" is Apache 2.2 syntax and is silently ignored where
         * AllowOverride does not grant Limit - which is the case on this host,
         * proven by the fact that this .htaccess was itself downloadable over
         * HTTP. Write all three forms: the 2.4 directive, the 2.2 one for old
         * servers, and a rewrite, which falls in the FileInfo class that hosts
         * are far more likely to allow.
         */
        $rules = "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Order Deny,Allow\n"
            . "    Deny from all\n"
            . "</IfModule>\n"
            . "<IfModule mod_rewrite.c>\n"
            . "    RewriteEngine On\n"
            . "    RewriteRule ^ - [F,L]\n"
            . "</IfModule>\n";

        $htaccess = $target . '.htaccess';
        $current  = file_exists( $htaccess ) ? (string) file_get_contents( $htaccess ) : '';
        // Also replaces the old 2.2-only file left by earlier versions.
        if ( strpos( $current, 'mod_rewrite.c' ) === false ) {
            file_put_contents( $htaccess, $rules );
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
        $destination = self::get_storage_dir() . $filename;

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
            $path = self::locate_document( $file );
            if ( $path !== '' ) {
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
                foreach ( array( self::get_private_dir(), self::get_legacy_dir() ) as $gb_dir ) {
                    if ( $gb_dir === '' ) {
                        continue;
                    }
                    $path = $gb_dir . basename( (string) $meta['file'] );
                    if ( file_exists( $path ) ) {
                        @unlink( $path );
                    }
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

        $cleaner_id = self::get_cleaner_id_for_user( get_current_user_id() );
        $documents  = $cleaner_id ? self::get_cleaner_verification_documents( $cleaner_id ) : array();
        $fields     = self::get_document_fields();

        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="gb-form">
            <?php wp_nonce_field( 'gb_upload_doc', 'gb_doc_nonce' ); ?>
            <?php foreach ( $fields as $field => $label ) : ?>
                <p>
                    <label>
                        <?php echo esc_html( $label ); ?>
                        <?php if ( ! empty( $documents[ $field ] ) ) : ?>
                            <em style="color:green;"> &#10003; Uploaded</em>
                        <?php endif; ?>
                    </label>
                    <input type="file" name="gb_document[<?php echo esc_attr( $field ); ?>]" accept="image/*,.pdf">
                </p>
            <?php endforeach; ?>
            <button type="submit" class="gb-btn">Upload Documents</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_upload_submission() {
        if ( ! isset( $_POST['gb_doc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gb_doc_nonce'] ) ), 'gb_upload_doc' ) ) {
            return;
        }

        $cleaner_id = self::get_cleaner_id_for_user( get_current_user_id() );
        if ( $cleaner_id < 1 || empty( $_FILES['gb_document'] ) ) {
            return;
        }

        $allowed_fields = self::get_document_fields();
        foreach ( array_keys( $allowed_fields ) as $field ) {
            if ( ! empty( $_FILES['gb_document']['name'][ $field ] ) ) {
                $file = array(
                    'name'     => $_FILES['gb_document']['name'][ $field ],
                    'type'     => $_FILES['gb_document']['type'][ $field ],
                    'tmp_name' => $_FILES['gb_document']['tmp_name'][ $field ],
                    'error'    => $_FILES['gb_document']['error'][ $field ],
                    'size'     => $_FILES['gb_document']['size'][ $field ],
                );
                self::store_cleaner_verification_document( $cleaner_id, $field, $file );
            }
        }
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

        $filepath = self::locate_document( $document['file'] );
        if ( $filepath === '' ) {
            wp_die( 'File not found.' );
        }
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
