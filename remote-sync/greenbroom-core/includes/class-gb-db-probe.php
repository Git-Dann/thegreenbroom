<?php
/**
 * TEMPORARY: can this host support real database tables?
 *
 * Before moving bookings and memberships out of post meta and into proper
 * tables, it is worth knowing for certain rather than assuming: whether the
 * database user can create a table, whether InnoDB and transactions are
 * available, and - the point of the whole exercise - whether a UNIQUE
 * constraint is actually enforced, which is what makes a double booking
 * impossible rather than merely unlikely.
 *
 * Creates one throwaway table, tests it, drops it, writes the findings to a
 * file named at deploy time, and never runs again. Touches nothing else.
 * Delete this file once the answer is known.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_run_db_capability_probe() {
    if ( get_option( 'gb_db_probe_done' ) ) {
        return;
    }
    update_option( 'gb_db_probe_done', 1 );

    global $wpdb;
    $table   = $wpdb->prefix . 'gb_probe_tmp';
    $results = array();

    $results['mysql_version']  = $wpdb->get_var( 'SELECT VERSION()' );
    $results['charset_collate'] = $wpdb->get_charset_collate();
    $results['prefix']          = $wpdb->prefix;

    // 1. Can we create a table at all?
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        cleaner_id bigint(20) unsigned NOT NULL,
        slot_date date NOT NULL,
        slot_time time NOT NULL,
        created datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY cleaner_slot (cleaner_id, slot_date, slot_time),
        KEY slot_date (slot_date)
    ) " . $wpdb->get_charset_collate() . ";";
    dbDelta( $sql );

    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    $results['create_table'] = $exists;

    if ( $exists ) {
        // 2. Is the storage engine one that supports transactions?
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        ) );
        $results['engine'] = $row ? $row->ENGINE : 'unknown';

        // 3. THE important one. Does a UNIQUE key actually stop a duplicate?
        $first = $wpdb->insert( $table, array(
            'cleaner_id' => 1, 'slot_date' => '2030-01-01', 'slot_time' => '09:00:00', 'created' => current_time( 'mysql' ),
        ) );
        $wpdb->suppress_errors( true );
        $second = $wpdb->insert( $table, array(
            'cleaner_id' => 1, 'slot_date' => '2030-01-01', 'slot_time' => '09:00:00', 'created' => current_time( 'mysql' ),
        ) );
        $wpdb->suppress_errors( false );
        $results['first_insert']      = $first !== false;
        $results['duplicate_blocked'] = $second === false;
        $results['row_count']         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

        // 4. Do transactions roll back?
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->insert( $table, array(
            'cleaner_id' => 2, 'slot_date' => '2030-01-02', 'slot_time' => '10:00:00', 'created' => current_time( 'mysql' ),
        ) );
        $wpdb->query( 'ROLLBACK' );
        $results['transaction_rollback'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) === 1;

        // 5. Clean up entirely.
        $wpdb->query( "DROP TABLE IF EXISTS $table" );
        $results['dropped'] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table;
    }

    $uploads = wp_get_upload_dir();
    if ( ! empty( $uploads['basedir'] ) ) {
        @file_put_contents( trailingslashit( $uploads['basedir'] ) . 'gb-dbcheck-Zt89hQyqoU8LWfyxTPXF.json', wp_json_encode( $results ) );
    }
}
add_action( 'init', 'gb_run_db_capability_probe', 40 );
