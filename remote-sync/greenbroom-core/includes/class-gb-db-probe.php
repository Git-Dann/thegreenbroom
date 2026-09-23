<?php
/**
 * TEMPORARY: removes the database capability probe's result file.
 *
 * The probe has answered its question. Its result file names the database
 * table prefix, which is minor but pointless to leave lying in a public
 * folder, so this deletes it and the options the probe set. The whole file
 * goes in the next deploy.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_cleanup_db_capability_probe() {
    if ( get_option( 'gb_db_probe_cleaned' ) ) {
        return;
    }

    $uploads = wp_get_upload_dir();
    if ( ! empty( $uploads['basedir'] ) ) {
        $file = trailingslashit( $uploads['basedir'] ) . 'gb-dbcheck-Zt89hQyqoU8LWfyxTPXF.json';
        if ( file_exists( $file ) ) {
            @unlink( $file );
        }
    }

    delete_option( 'gb_db_probe_done' );
    update_option( 'gb_db_probe_cleaned', 1 );
}
add_action( 'init', 'gb_cleanup_db_capability_probe', 40 );
