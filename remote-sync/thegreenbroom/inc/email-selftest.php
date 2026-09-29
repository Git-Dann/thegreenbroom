<?php
/**
 * Email self-test, run from GitHub Actions (.github/workflows/email-selftest.yml).
 *
 * Sends real test emails to an address of your choosing through the real
 * chain, and answers with exactly what happened on each route, so a send can
 * be proved end to end without anybody logging in to the dashboard.
 *
 * How it is locked. The workflow signs in to the server over SSH - the same
 * credentials a deploy uses - and leaves a one-time random token in a PHP file
 * in the theme. It then posts that token here. The token is read and the file
 * deleted before anything is compared, so each token works once at most; a
 * token older than ten minutes is refused; and being a PHP file that only
 * returns a value, it prints nothing if fetched over the web. Without SSH
 * access to the server, there is no way to make this send anything.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_email_selftest_token_file() {
    return get_template_directory() . '/.gb-selftest-token.php';
}

add_action( 'init', function () {
    if ( empty( $_GET['gb_email_selftest'] ) ) {
        return;
    }

    nocache_headers();
    gb_email_selftest_handle();
}, 2 );

function gb_email_selftest_respond( $code, array $body ) {
    status_header( $code );
    header( 'Content-Type: application/json; charset=utf-8' );
    echo wp_json_encode( $body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    exit;
}

function gb_email_selftest_handle() {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        gb_email_selftest_respond( 405, array( 'error' => 'POST only.' ) );
    }

    $file = gb_email_selftest_token_file();

    if ( ! is_readable( $file ) ) {
        gb_email_selftest_respond( 403, array( 'error' => 'No test has been set up on the server.' ) );
    }

    // Single use: read it, then delete it, before looking at what was sent.
    $age  = time() - (int) filemtime( $file );
    $data = include $file;
    @unlink( $file );

    $given = isset( $_POST['token'] ) ? (string) wp_unslash( $_POST['token'] ) : '';

    if ( ! is_array( $data ) || empty( $data['token'] ) || $age > 10 * MINUTE_IN_SECONDS || ! hash_equals( (string) $data['token'], $given ) ) {
        gb_email_selftest_respond( 403, array( 'error' => 'Token refused.' ) );
    }

    $to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
    if ( ! is_email( $to ) ) {
        gb_email_selftest_respond( 400, array( 'error' => 'Give a valid address to send to.' ) );
    }

    $report = array(
        'site'   => home_url( '/' ),
        'at'     => gmdate( 'c' ),
        'to'     => $to,
        'health' => gb_outbox_health_check(),
        'tests'  => array(),
    );

    $stamp = wp_date( 'j M Y, H:i' );

    /*
     * 1. The normal route, exactly as a real email would take it: Brevo with
     *    a real branded template first, then whatever the chain falls to.
     */
    unset( $GLOBALS['gb_brevo_failed_this_request'] );
    $sent = gb_send_brevo_template_email(
        $to,
        'The Green Broom email test (1 of 2): normal route, ' . $stamp,
        "This is a test from The Green Broom website, sent the way every real email is sent.\n\nIf it arrived, email is working.",
        array(),
        gb_get_brevo_template_id( 'support_reply' ),
        array(
            'first_name'    => 'there',
            'reply_message' => "This is a test from The Green Broom website, sent at {$stamp} the way every real email is sent: Brevo first, then the backup mailbox, then the web server.\n\nIf you are reading this, email is working.",
            'inbox_url'     => home_url( '/contact-support/' ),
        )
    );
    $report['tests']['normal_route'] = gb_email_selftest_row( $sent );

    /*
     * 2. The backup mailbox on its own, skipping Brevo and with no rescue by
     *    the web server, to prove the backup before the day it is needed.
     */
    if ( gb_backup_smtp_configured() ) {
        $GLOBALS['gb_outbox_backup_only'] = true;
        $GLOBALS['gb_outbox_kind']        = 'test';
        $sent = wp_mail(
            $to,
            'The Green Broom email test (2 of 2): backup mailbox, ' . $stamp,
            "This test skipped Brevo and went through the backup mailbox on its own.\n\nIf it arrived, the backup route is working, and email will still go out if Brevo ever refuses.",
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );
        unset( $GLOBALS['gb_outbox_backup_only'] );
        $report['tests']['backup_mailbox'] = gb_email_selftest_row( $sent );
    } else {
        $report['tests']['backup_mailbox'] = array( 'skipped' => 'The backup mailbox is not set up yet (Settings -> Email & delivery).' );
    }

    gb_email_selftest_respond( 200, $report );
}

function gb_email_selftest_row( $sent ) {
    $row = ! empty( $GLOBALS['gb_outbox_last_id'] ) ? gb_outbox_get( (int) $GLOBALS['gb_outbox_last_id'] ) : null;

    return array(
        'handed_over' => (bool) $sent,
        'status'      => $row ? $row->status : 'not logged',
        'sent_by'     => $row && $row->status === 'sent' ? gb_outbox_route_label( $row->route ) : '',
        'message_id'  => $row && isset( $row->message_id ) ? $row->message_id : '',
        'trail'       => $row ? trim( (string) $row->last_error ) : '',
    );
}
