<?php
/**
 * Outbound email: every message logged, retried, and given a second route.
 *
 * Why this exists. On 29 September a real signup could not get in because the
 * verification email never arrived. Three separate things went wrong, and any
 * one of them being handled would have saved it:
 *
 *   1. Brevo refused the send (its IP allow-list did not include the server),
 *      and the site took that refusal as final and destroyed the message.
 *   2. Nothing recorded that it had happened, so the first anybody knew was
 *      the person telling us.
 *   3. There was no second way to send it. The fallback added in a hurry went
 *      out from the web server, which is not in the domain's SPF record and
 *      does not sign mail, so Gmail would reject or bin it anyway.
 *
 * So every email now goes through a chain, and every step is written down:
 *
 *   Brevo API  ->  backup SMTP mailbox  ->  the server's own mail  ->  retry queue
 *
 * and a failure at the end of the chain is not the end either: it is retried
 * on a backoff for several hours, shown on the manager dashboard, and can be
 * resent by hand.
 *
 * The one rule this file keeps above all others: it must never be the reason
 * an email fails. Every piece of logging is best-effort. If the table is
 * missing, the database is down or a column is wrong, sending carries on
 * exactly as it would have without any of this.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_OUTBOX_DB_VERSION   = 1;
const GB_OUTBOX_MAX_ATTEMPTS = 5;   // The first send plus four retries.
const GB_OUTBOX_KEEP_DAYS    = 30;  // How long the log keeps a line about each email.
const GB_OUTBOX_PAYLOAD_HOURS = 48; // How long a failed email's content is kept for resending.

/**
 * Gaps between attempts, after the first failure, the second, and so on.
 *
 * Five minutes catches a blip. Six hours at the end still reaches somebody the
 * same day. A verification link lasts 24 hours, so there is no point going
 * much further than this - and the link would be dead by the time it landed.
 */
function gb_outbox_retry_delays() {
    return array( 5 * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS );
}

/**
 * Kinds of email where a newer one makes an older one worthless.
 *
 * Each of these carries a single-use link, and sending a new one invalidates
 * the last. Retrying an old one after the person has pressed Resend would
 * deliver a link that no longer works - worse than not retrying, because it
 * looks like the site is broken when they click it.
 */
function gb_outbox_superseding_kinds() {
    return array( 'verification', 'password_reset' );
}

function gb_outbox_table() {
    global $wpdb;

    return $wpdb->prefix . 'gb_email_log';
}

/* --------------------------------------------------------------------------
   STORAGE
   -------------------------------------------------------------------------- */

function gb_outbox_install() {
    if ( (int) get_option( 'gb_outbox_db_version', 0 ) >= GB_OUTBOX_DB_VERSION ) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = gb_outbox_table();
    $charset = $wpdb->get_charset_collate();

    // dbDelta is fussy: one field per line, two spaces after PRIMARY KEY.
    $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  to_email varchar(255) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  kind varchar(40) NOT NULL DEFAULT 'general',
  status varchar(20) NOT NULL DEFAULT 'sending',
  route varchar(20) NOT NULL DEFAULT '',
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  last_error text NULL,
  next_attempt_at datetime NULL,
  payload longtext NULL,
  PRIMARY KEY  (id),
  KEY status_next (status,next_attempt_at),
  KEY created_at (created_at),
  KEY to_kind (to_email(100),kind)
) {$charset};";

    dbDelta( $sql );

    // Only record the version if the table really exists, so a failed
    // install is tried again on the next request rather than never.
    if ( gb_outbox_ready( true ) ) {
        update_option( 'gb_outbox_db_version', GB_OUTBOX_DB_VERSION, false );
    }
}
add_action( 'init', 'gb_outbox_install', 5 );

/**
 * Is the log usable? Checked once per request.
 */
function gb_outbox_ready( $recheck = false ) {
    static $ready = null;

    if ( $ready !== null && ! $recheck ) {
        return $ready;
    }

    global $wpdb;
    $table = gb_outbox_table();
    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    $ready = ( $found === $table );

    return $ready;
}

function gb_outbox_now( $offset = 0 ) {
    return gmdate( 'Y-m-d H:i:s', time() + (int) $offset );
}

/**
 * Best guess at what kind of email this is, for the log and for superseding.
 */
function gb_outbox_guess_kind( $subject, $hint = '' ) {
    if ( $hint !== '' ) {
        return sanitize_key( $hint );
    }

    $subject = strtolower( (string) $subject );

    if ( strpos( $subject, 'verify' ) !== false ) {
        return 'verification';
    }
    if ( strpos( $subject, 'password' ) !== false ) {
        return 'password_reset';
    }
    if ( strpos( $subject, 'booking' ) !== false ) {
        return 'booking';
    }
    if ( strpos( $subject, 'test' ) !== false ) {
        return 'test';
    }

    return 'general';
}

function gb_outbox_flatten_to( $to ) {
    if ( is_array( $to ) ) {
        $to = implode( ', ', array_map( 'strval', $to ) );
    }

    return substr( sanitize_text_field( (string) $to ), 0, 255 );
}

/**
 * Start a line in the log for an email that is about to be sent.
 *
 * The content is stored now, not after a failure, so that a request which
 * dies half way through sending leaves something that can still be retried.
 * It is cleared the moment the email is confirmed sent.
 *
 * @return int The log id, or 0 if the log is not available.
 */
function gb_outbox_begin( array $atts, $kind = '' ) {
    if ( ! gb_outbox_ready() ) {
        return 0;
    }

    global $wpdb;

    $to      = gb_outbox_flatten_to( isset( $atts['to'] ) ? $atts['to'] : '' );
    $subject = substr( sanitize_text_field( (string) ( isset( $atts['subject'] ) ? $atts['subject'] : '' ) ), 0, 255 );
    $kind    = gb_outbox_guess_kind( $subject, $kind );

    /*
     * A new single-use link makes any still-pending one for the same person
     * worthless. Stand those down rather than let a retry deliver a dead link
     * an hour after the working one.
     */
    if ( in_array( $kind, gb_outbox_superseding_kinds(), true ) && $to !== '' ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . gb_outbox_table() . "
                SET status = 'superseded', payload = NULL, next_attempt_at = NULL, updated_at = %s
              WHERE to_email = %s AND kind = %s AND status IN ('retrying','sending','dead')",
            gb_outbox_now(),
            $to,
            $kind
        ) );
    }

    $payload = wp_json_encode( array(
        'to'      => isset( $atts['to'] ) ? $atts['to'] : '',
        'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
        'message' => isset( $atts['message'] ) ? (string) $atts['message'] : '',
        'headers' => isset( $atts['headers'] ) ? $atts['headers'] : array(),
    ) );

    $inserted = $wpdb->insert( gb_outbox_table(), array(
        'created_at' => gb_outbox_now(),
        'updated_at' => gb_outbox_now(),
        'to_email'   => $to,
        'subject'    => $subject,
        'kind'       => $kind,
        'status'     => 'sending',
        'attempts'   => 1,
        'payload'    => $payload ? $payload : null,
    ) );

    return $inserted ? (int) $wpdb->insert_id : 0;
}

function gb_outbox_update( $id, array $fields ) {
    $id = (int) $id;

    if ( $id < 1 || ! gb_outbox_ready() ) {
        return;
    }

    global $wpdb;
    $fields['updated_at'] = gb_outbox_now();
    $wpdb->update( gb_outbox_table(), $fields, array( 'id' => $id ) );
}

function gb_outbox_get( $id ) {
    $id = (int) $id;

    if ( $id < 1 || ! gb_outbox_ready() ) {
        return null;
    }

    global $wpdb;

    return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gb_outbox_table() . ' WHERE id = %d', $id ) );
}

/**
 * Add to the error trail without losing what came before.
 *
 * "Brevo refused, sent by the backup instead" is the most useful line in the
 * whole log, so an earlier route's failure is kept even after a later route
 * succeeds.
 */
function gb_outbox_note_error( $id, $route, $message ) {
    $row = gb_outbox_get( $id );

    if ( ! $row ) {
        return;
    }

    $line  = '[' . gmdate( 'H:i' ) . ' ' . sanitize_key( $route ) . '] ' . sanitize_text_field( (string) $message );
    $trail = trim( (string) $row->last_error );
    $trail = $trail === '' ? $line : $trail . "\n" . $line;

    // Keep the tail if it grows; the latest reasons are the useful ones.
    if ( strlen( $trail ) > 2000 ) {
        $trail = substr( $trail, -2000 );
    }

    gb_outbox_update( $id, array( 'last_error' => $trail ) );
}

function gb_outbox_mark_sent( $id, $route ) {
    gb_outbox_update( $id, array(
        'status'          => 'sent',
        'route'           => sanitize_key( $route ),
        'payload'         => null,
        'next_attempt_at' => null,
    ) );
}

/**
 * Every route failed on this attempt. Queue it again, or give up and say so.
 */
function gb_outbox_mark_failed( $id, $message ) {
    $row = gb_outbox_get( $id );

    if ( ! $row ) {
        return;
    }

    // Already stood down by a newer email to the same person; leave it be.
    if ( $row->status === 'superseded' ) {
        return;
    }

    $attempts = (int) $row->attempts;
    $delays   = gb_outbox_retry_delays();

    // A test is reported on the spot; retrying it later helps nobody.
    if ( $row->kind === 'test' ) {
        gb_outbox_update( $id, array( 'status' => 'dead', 'payload' => null, 'next_attempt_at' => null ) );

        return;
    }

    if ( $attempts >= GB_OUTBOX_MAX_ATTEMPTS || empty( $row->payload ) || ! isset( $delays[ $attempts - 1 ] ) ) {
        gb_outbox_update( $id, array( 'status' => 'dead', 'next_attempt_at' => null ) );

        if ( $row->kind !== 'alert' ) {
            gb_outbox_queue_alert( 'An email could not be delivered after ' . $attempts . ' attempts: "' . $row->subject . '" to ' . $row->to_email . '. Last reason: ' . $message );
        }

        return;
    }

    $next = time() + (int) $delays[ $attempts - 1 ];

    gb_outbox_update( $id, array(
        'status'          => 'retrying',
        'next_attempt_at' => gmdate( 'Y-m-d H:i:s', $next ),
    ) );

    // Cheap marker so ordinary page loads know when to bother checking.
    $due = (int) get_option( 'gb_outbox_next_due', 0 );
    if ( $due === 0 || $next < $due ) {
        update_option( 'gb_outbox_next_due', $next, false );
    }
}

/* --------------------------------------------------------------------------
   HOOKING INTO wp_mail
   --------------------------------------------------------------------------
   Every email the site sends - ours, WordPress core's password reset, any
   plugin's - passes through wp_mail, so that is where the chain lives.

     pre_wp_mail (priority 1)   opens the log line
     pre_wp_mail (priority 10)  Brevo: sent, or hands on (cleaner-profiles.php)
     pre_wp_mail (priority 20)  the backup mailbox: sent, or hands on
     then WordPress itself      the server's own mail, as the last resort
     wp_mail_succeeded          closes the line as sent
     wp_mail_failed             closes it as failed, which queues a retry

   The backup mailbox is tried as a separate, nested wp_mail call rather than
   by quietly reconfiguring the one in progress. That is what lets a failure
   there fall through to the next route within the same send, and still hand
   the caller an honest true or false at the end.
   -------------------------------------------------------------------------- */

function gb_outbox_on_pre_wp_mail( $pre, $atts ) {
    if ( $pre !== null ) {
        return $pre;
    }

    // The backup-mailbox attempt belongs to the line already open.
    if ( ! empty( $GLOBALS['gb_outbox_inner'] ) ) {
        $GLOBALS['gb_outbox_route'] = 'smtp_backup';

        return $pre;
    }

    if ( ! empty( $GLOBALS['gb_outbox_retry'] ) ) {
        // A retry already owns its line.
        $id = (int) $GLOBALS['gb_outbox_retry'];
    } elseif ( ! empty( $GLOBALS['gb_outbox_carry'] ) ) {
        // Handed on by the branded-template sender after Brevo refused it,
        // so this is the same email and keeps the same line.
        $id = (int) $GLOBALS['gb_outbox_carry'];
        unset( $GLOBALS['gb_outbox_carry'] );
    } else {
        $kind = isset( $GLOBALS['gb_outbox_kind'] ) ? (string) $GLOBALS['gb_outbox_kind'] : '';
        unset( $GLOBALS['gb_outbox_kind'] );
        $id = gb_outbox_begin( is_array( $atts ) ? $atts : array(), $kind );
    }

    $GLOBALS['gb_outbox_current'] = $id;
    $GLOBALS['gb_outbox_last_id'] = $id;
    $GLOBALS['gb_outbox_route']   = 'php_mail';

    return $pre;
}
add_filter( 'pre_wp_mail', 'gb_outbox_on_pre_wp_mail', 1, 2 );

/**
 * Second route: the backup mailbox, when Brevo has not already sent it.
 */
function gb_outbox_try_backup_route( $pre, $atts ) {
    if ( $pre !== null || ! empty( $GLOBALS['gb_outbox_inner'] ) ) {
        return $pre;
    }

    $backup_only = ! empty( $GLOBALS['gb_outbox_backup_only'] );

    if ( ! gb_backup_smtp_configured() ) {
        return $backup_only ? false : null;
    }

    $atts = is_array( $atts ) ? $atts : array();

    $GLOBALS['gb_outbox_inner'] = true;
    $sent = wp_mail(
        isset( $atts['to'] ) ? $atts['to'] : '',
        isset( $atts['subject'] ) ? $atts['subject'] : '',
        isset( $atts['message'] ) ? $atts['message'] : '',
        isset( $atts['headers'] ) ? $atts['headers'] : array(),
        isset( $atts['attachments'] ) ? $atts['attachments'] : array()
    );
    unset( $GLOBALS['gb_outbox_inner'] );

    if ( $sent ) {
        return true;
    }

    // A test of the backup should report the backup, not be rescued by
    // something else and look like it passed.
    if ( $backup_only ) {
        $id = ! empty( $GLOBALS['gb_outbox_current'] ) ? (int) $GLOBALS['gb_outbox_current'] : 0;
        gb_outbox_update( $id, array( 'status' => 'dead', 'payload' => null, 'next_attempt_at' => null ) );
        unset( $GLOBALS['gb_outbox_current'] );

        return false;
    }

    // Carry on to the server's own mail.
    $GLOBALS['gb_outbox_route'] = 'php_mail';

    return null;
}
add_filter( 'pre_wp_mail', 'gb_outbox_try_backup_route', 20, 2 );

add_action( 'wp_mail_succeeded', function ( $mail_data ) {
    $id = ! empty( $GLOBALS['gb_outbox_current'] ) ? (int) $GLOBALS['gb_outbox_current'] : 0;

    if ( $id < 1 ) {
        $id = gb_outbox_begin( is_array( $mail_data ) ? $mail_data : array() );
    }

    gb_outbox_mark_sent( $id, isset( $GLOBALS['gb_outbox_route'] ) ? $GLOBALS['gb_outbox_route'] : 'php_mail' );
    unset( $GLOBALS['gb_outbox_current'] );
} );

add_action( 'wp_mail_failed', function ( $error ) {
    $id = ! empty( $GLOBALS['gb_outbox_current'] ) ? (int) $GLOBALS['gb_outbox_current'] : 0;

    if ( $id < 1 && is_wp_error( $error ) ) {
        $data = $error->get_error_data();
        $id   = gb_outbox_begin( is_array( $data ) ? $data : array() );
    }

    $message = is_wp_error( $error ) ? $error->get_error_message() : 'The mail server would not accept it.';
    $route   = isset( $GLOBALS['gb_outbox_route'] ) ? $GLOBALS['gb_outbox_route'] : 'php_mail';

    gb_outbox_note_error( $id, $route, $message );

    // The backup mailbox failing is not the end of this send: the server's own
    // mail is tried next, and that attempt decides the outcome.
    if ( ! empty( $GLOBALS['gb_outbox_inner'] ) ) {
        return;
    }

    gb_outbox_mark_failed( $id, $message );
    unset( $GLOBALS['gb_outbox_current'] );
} );

/**
 * For senders that talk to Brevo directly, outside wp_mail: open the line, or
 * carry on with the one this email already has.
 */
function gb_outbox_open_line( array $atts, $kind = '' ) {
    if ( ! empty( $GLOBALS['gb_outbox_bridge_id'] ) ) {
        $id = (int) $GLOBALS['gb_outbox_bridge_id'];
    } elseif ( ! empty( $GLOBALS['gb_outbox_retry'] ) ) {
        $id = (int) $GLOBALS['gb_outbox_retry'];
    } else {
        $id = gb_outbox_begin( $atts, $kind );
    }

    $GLOBALS['gb_outbox_last_id'] = $id;

    return $id;
}

/**
 * About to fall back to wp_mail with the same email: make sure it lands on
 * the same log line, or, if none is open yet, that the new one is labelled.
 */
function gb_outbox_hand_to_wp_mail( $id, $kind = '' ) {
    if ( (int) $id < 1 && ! empty( $GLOBALS['gb_outbox_bridge_id'] ) ) {
        $id = (int) $GLOBALS['gb_outbox_bridge_id'];
    }

    if ( (int) $id > 0 ) {
        $GLOBALS['gb_outbox_carry'] = (int) $id;
    } elseif ( $kind !== '' ) {
        $GLOBALS['gb_outbox_kind'] = $kind;
    }
}

/**
 * What happened to the email the site tried to send most recently.
 *
 * @return string '', 'sent', 'retrying', 'dead', 'sending' or 'superseded'.
 */
function gb_outbox_last_status() {
    $row = ! empty( $GLOBALS['gb_outbox_last_id'] ) ? gb_outbox_get( (int) $GLOBALS['gb_outbox_last_id'] ) : null;

    return $row ? (string) $row->status : '';
}

/* --------------------------------------------------------------------------
   THE BACKUP ROUTE
   --------------------------------------------------------------------------
   A real mailbox on the domain's own mail host. Independent of Brevo - a
   different company, different servers, different credentials - and, unlike
   the web server, it is named in the domain's SPF record, so mail sent this
   way is authenticated and gets past Gmail.
   -------------------------------------------------------------------------- */

function gb_get_backup_smtp_settings() {
    $settings_id = function_exists( 'gb_get_site_settings_id' ) ? gb_get_site_settings_id() : (int) get_option( 'page_on_front' );

    $port   = (int) get_post_meta( $settings_id, '_gb_backup_smtp_port', true );
    $secure = sanitize_key( (string) get_post_meta( $settings_id, '_gb_backup_smtp_secure', true ) );
    $host   = trim( (string) get_post_meta( $settings_id, '_gb_backup_smtp_host', true ) );

    return array(
        'enabled'  => get_post_meta( $settings_id, '_gb_backup_smtp_enabled', true ) === 'yes',
        'host'     => $host !== '' ? $host : 'smtp.livemail.co.uk',
        'port'     => $port > 0 ? $port : 587,
        'secure'   => in_array( $secure, array( 'tls', 'ssl', 'none' ), true ) ? $secure : 'tls',
        'username' => sanitize_email( (string) get_post_meta( $settings_id, '_gb_backup_smtp_user', true ) ),
        'password' => (string) get_post_meta( $settings_id, '_gb_backup_smtp_pass', true ),
    );
}

function gb_backup_smtp_configured( $cfg = null ) {
    $cfg = is_array( $cfg ) ? $cfg : gb_get_backup_smtp_settings();

    return $cfg['enabled'] && $cfg['host'] !== '' && is_email( $cfg['username'] ) && $cfg['password'] !== '';
}

function gb_backup_smtp_apply( $phpmailer, array $cfg ) {
    $phpmailer->isSMTP();
    $phpmailer->Host        = $cfg['host'];
    $phpmailer->Port        = (int) $cfg['port'];
    $phpmailer->SMTPAuth    = true;
    $phpmailer->Username    = $cfg['username'];
    $phpmailer->Password    = $cfg['password'];
    $phpmailer->SMTPSecure  = $cfg['secure'] === 'none' ? '' : $cfg['secure'];
    $phpmailer->SMTPAutoTLS = $cfg['secure'] !== 'none';
    $phpmailer->Timeout     = 15;
}

/*
 * Only the nested backup attempt is pointed at the mailbox. wp_mail resets
 * PHPMailer to the server's own mail() at the start of every call, so the
 * last-resort attempt that follows a failed backup goes out the plain way.
 */
add_action( 'phpmailer_init', function ( $phpmailer ) {
    if ( empty( $GLOBALS['gb_outbox_inner'] ) ) {
        return;
    }

    $cfg = gb_get_backup_smtp_settings();

    if ( ! gb_backup_smtp_configured( $cfg ) ) {
        return;
    }

    gb_backup_smtp_apply( $phpmailer, $cfg );

    /*
     * Send as the mailbox that is signing in. Mail hosts commonly refuse to
     * relay for an address other than the authenticated one, and the envelope
     * sender is what SPF checks. If the From was something else, it becomes the
     * Reply-To, so replies still go where they were meant to.
     */
    $phpmailer->Sender = $cfg['username'];

    if ( strcasecmp( (string) $phpmailer->From, $cfg['username'] ) !== 0 ) {
        if ( is_email( $phpmailer->From ) && empty( $phpmailer->getReplyToAddresses() ) ) {
            $phpmailer->addReplyTo( $phpmailer->From, $phpmailer->FromName );
        }
        $phpmailer->From = $cfg['username'];
    }

    $GLOBALS['gb_outbox_route'] = 'smtp_backup';
}, 20 );

/**
 * Can we sign in to the backup mailbox? Connects and authenticates without
 * sending anything, so it is safe to run on a timer.
 *
 * @return array{ok: bool, message: string, configured: bool}
 */
function gb_backup_smtp_check() {
    $cfg = gb_get_backup_smtp_settings();

    if ( ! gb_backup_smtp_configured( $cfg ) ) {
        return array( 'ok' => false, 'configured' => false, 'message' => 'Not set up yet.' );
    }

    if ( ! class_exists( '\PHPMailer\PHPMailer\PHPMailer' ) ) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer( true );
        gb_backup_smtp_apply( $mail, $cfg );
        $mail->Timeout = 10;
        $ok = $mail->smtpConnect();
        $mail->smtpClose();

        return $ok
            ? array( 'ok' => true, 'configured' => true, 'message' => 'Signed in to ' . $cfg['host'] . ' as ' . $cfg['username'] . '.' )
            : array( 'ok' => false, 'configured' => true, 'message' => 'Could not sign in to ' . $cfg['host'] . '.' );
    } catch ( \Exception $e ) {
        return array( 'ok' => false, 'configured' => true, 'message' => sanitize_text_field( $e->getMessage() ) );
    }
}

/* --------------------------------------------------------------------------
   RETRYING
   -------------------------------------------------------------------------- */

/**
 * Send one queued email again, through the whole chain.
 *
 * @return bool Whether it went this time.
 */
function gb_outbox_retry_one( $id ) {
    $row = gb_outbox_get( $id );

    if ( ! $row || empty( $row->payload ) || $row->status === 'superseded' || $row->status === 'sent' ) {
        return false;
    }

    $payload = json_decode( (string) $row->payload, true );

    if ( ! is_array( $payload ) || empty( $payload['to'] ) ) {
        gb_outbox_update( $id, array( 'status' => 'dead', 'next_attempt_at' => null ) );
        gb_outbox_note_error( $id, 'queue', 'The stored copy of this email could not be read.' );

        return false;
    }

    gb_outbox_update( $id, array(
        'status'   => 'sending',
        'attempts' => (int) $row->attempts + 1,
    ) );

    $GLOBALS['gb_outbox_retry'] = (int) $id;

    $sent = wp_mail(
        $payload['to'],
        isset( $payload['subject'] ) ? $payload['subject'] : '',
        isset( $payload['message'] ) ? $payload['message'] : '',
        isset( $payload['headers'] ) ? $payload['headers'] : array()
    );

    unset( $GLOBALS['gb_outbox_retry'] );

    return (bool) $sent;
}

/**
 * Work through whatever is due.
 */
function gb_outbox_process_queue( $limit = 10 ) {
    if ( ! gb_outbox_ready() ) {
        return;
    }

    // One runner at a time. A lock that outlives its runner expires by itself.
    if ( get_transient( 'gb_outbox_lock' ) ) {
        return;
    }
    set_transient( 'gb_outbox_lock', 1, 4 * MINUTE_IN_SECONDS );

    global $wpdb;
    $table = gb_outbox_table();

    // A request that died half way through a send leaves a line stuck at
    // "sending" with its content still attached. Pick those up too.
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$table} SET status = 'retrying', next_attempt_at = %s
          WHERE status = 'sending' AND payload IS NOT NULL AND updated_at < %s",
        gb_outbox_now(),
        gb_outbox_now( -15 * MINUTE_IN_SECONDS )
    ) );

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$table}
          WHERE status = 'retrying' AND next_attempt_at <= %s
          ORDER BY next_attempt_at ASC LIMIT %d",
        gb_outbox_now(),
        max( 1, (int) $limit )
    ) );

    foreach ( $ids as $id ) {
        gb_outbox_retry_one( (int) $id );
    }

    // Recompute the marker for the next ordinary page load.
    $next = $wpdb->get_var(
        "SELECT MIN(next_attempt_at) FROM {$table} WHERE status = 'retrying' AND next_attempt_at IS NOT NULL"
    );
    update_option( 'gb_outbox_next_due', $next ? strtotime( $next . ' UTC' ) : 0, false );

    delete_transient( 'gb_outbox_lock' );
}
add_action( 'gb_outbox_process_queue', 'gb_outbox_process_queue' );

add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['gb_every_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Every five minutes' );

    return $schedules;
} );

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'gb_outbox_process_queue' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'gb_every_five_minutes', 'gb_outbox_process_queue' );
    }
    if ( ! wp_next_scheduled( 'gb_outbox_health_check' ) ) {
        wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'twicedaily', 'gb_outbox_health_check' );
    }
    if ( ! wp_next_scheduled( 'gb_outbox_cleanup' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gb_outbox_cleanup' );
    }
}, 20 );

/*
 * Do not depend on WP-cron alone. It only runs when an uncached page is
 * served, and LiteSpeed serves most of this site from cache, so on a quiet
 * day the queue could sit for hours. Any uncached request - a login, a
 * dashboard, a form - checks a single stored timestamp and, only if something
 * is due, finishes the response first and then works the queue, so nobody
 * waits for it.
 */
add_action( 'shutdown', function () {
    $due = (int) get_option( 'gb_outbox_next_due', 0 );

    if ( $due === 0 || $due > time() || get_transient( 'gb_outbox_lock' ) ) {
        return;
    }

    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    } elseif ( function_exists( 'litespeed_finish_request' ) ) {
        litespeed_finish_request();
    }

    gb_outbox_process_queue( 5 );
}, 99 );

/* --------------------------------------------------------------------------
   KEEPING AN EYE ON IT
   -------------------------------------------------------------------------- */

/**
 * Check both routes on a timer, so a broken one is found by us rather than by
 * a customer. The Brevo check hits the same API, through the same allow-list,
 * that refused the send on the 29th - it would have flagged it within hours.
 */
function gb_outbox_health_check() {
    $health = array( 'at' => time() );

    if ( function_exists( 'gb_is_brevo_configured' ) && gb_is_brevo_configured() ) {
        $result = gb_brevo_api_request( 'account' );
        $health['brevo'] = is_wp_error( $result )
            ? array( 'ok' => false, 'message' => $result->get_error_message() )
            : array( 'ok' => true, 'message' => 'Connected.' );
    } else {
        $health['brevo'] = array( 'ok' => false, 'message' => 'Not set up.' );
    }

    $health['backup'] = gb_backup_smtp_check();

    $previous = gb_outbox_health();
    update_option( 'gb_email_health', $health, false );

    // Say so when a route breaks, not every twelve hours while it stays broken.
    $was_ok = function ( $route ) use ( $previous ) {
        return ! is_array( $previous ) || ! empty( $previous[ $route ]['ok'] );
    };
    $problems = array();
    if ( function_exists( 'gb_is_brevo_configured' ) && gb_is_brevo_configured() && empty( $health['brevo']['ok'] ) && $was_ok( 'brevo' ) ) {
        $problems[] = 'Brevo is refusing the site: ' . $health['brevo']['message'];
    }
    if ( ! empty( $health['backup']['configured'] ) && empty( $health['backup']['ok'] ) && $was_ok( 'backup' ) ) {
        $problems[] = 'The backup mailbox will not let the site sign in: ' . $health['backup']['message'];
    }
    if ( $problems ) {
        gb_outbox_queue_alert( implode( ' ', $problems ) );
    }

    return $health;
}
add_action( 'gb_outbox_health_check', 'gb_outbox_health_check' );

function gb_outbox_health() {
    $health = get_option( 'gb_email_health' );

    return is_array( $health ) ? $health : null;
}

/**
 * Keep the log from growing forever, and drop failed emails' content once it
 * is too old to be worth resending. That content can hold single-use links,
 * so it is not kept a moment longer than it is useful.
 */
function gb_outbox_cleanup() {
    if ( ! gb_outbox_ready() ) {
        return;
    }

    global $wpdb;
    $table = gb_outbox_table();

    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$table} WHERE created_at < %s",
        gb_outbox_now( -GB_OUTBOX_KEEP_DAYS * DAY_IN_SECONDS )
    ) );

    $wpdb->query( $wpdb->prepare(
        "UPDATE {$table} SET payload = NULL WHERE status = 'dead' AND payload IS NOT NULL AND updated_at < %s",
        gb_outbox_now( -GB_OUTBOX_PAYLOAD_HOURS * HOUR_IN_SECONDS )
    ) );
}
add_action( 'gb_outbox_cleanup', 'gb_outbox_cleanup' );

/**
 * What the dashboard needs to know at a glance.
 *
 * @return array{sent: int, retrying: int, dead: int}
 */
function gb_outbox_counts( $hours = 24 ) {
    $counts = array( 'sent' => 0, 'retrying' => 0, 'dead' => 0, 'server_mail' => 0 );

    if ( ! gb_outbox_ready() ) {
        return $counts;
    }

    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        'SELECT status, COUNT(*) AS n FROM ' . gb_outbox_table() . " WHERE created_at >= %s AND kind <> 'test' GROUP BY status",
        gb_outbox_now( -(int) $hours * HOUR_IN_SECONDS )
    ) );

    foreach ( (array) $rows as $row ) {
        if ( isset( $counts[ $row->status ] ) ) {
            $counts[ $row->status ] = (int) $row->n;
        }
    }

    // Delivered, but by the weakest route: unsigned, from a server outside the
    // domain's SPF record, so quite possibly into spam. Worth knowing about.
    $counts['server_mail'] = (int) $wpdb->get_var( $wpdb->prepare(
        'SELECT COUNT(*) FROM ' . gb_outbox_table() . " WHERE status = 'sent' AND route = 'php_mail' AND kind NOT IN ('test','alert') AND created_at >= %s",
        gb_outbox_now( -(int) $hours * HOUR_IN_SECONDS )
    ) );

    return $counts;
}

function gb_outbox_recent( $limit = 25 ) {
    if ( ! gb_outbox_ready() ) {
        return array();
    }

    global $wpdb;

    return (array) $wpdb->get_results( $wpdb->prepare(
        'SELECT id, created_at, updated_at, to_email, subject, kind, status, route, attempts, last_error, next_attempt_at,
                (payload IS NOT NULL) AS resendable
           FROM ' . gb_outbox_table() . ' ORDER BY id DESC LIMIT %d',
        max( 1, (int) $limit )
    ) );
}

/**
 * Is anything wrong that a manager should see without going looking?
 */
function gb_outbox_needs_attention() {
    $counts = gb_outbox_counts( 48 );
    $health = gb_outbox_health();

    $brevo_down = $health
        && function_exists( 'gb_is_brevo_configured' ) && gb_is_brevo_configured()
        && isset( $health['brevo']['ok'] ) && ! $health['brevo']['ok'];

    $backup_down = $health && ! empty( $health['backup']['configured'] ) && empty( $health['backup']['ok'] );

    return array(
        'dead'        => $counts['dead'],
        'retrying'    => $counts['retrying'],
        'server_mail' => $counts['server_mail'],
        'brevo_down'  => (bool) $brevo_down,
        'backup_down' => (bool) $backup_down,
        'any'         => $counts['dead'] > 0 || $counts['retrying'] > 0 || $brevo_down || $backup_down,
    );
}

/* --------------------------------------------------------------------------
   TELLING SOMEBODY
   --------------------------------------------------------------------------
   The failure on the 29th was found by the person it happened to. These
   alerts make sure it is found by us first.

   They are sent at the end of the request, never from inside another send:
   an email started while one is still in flight would take over its log
   line, and the page waiting on that first email would be told the wrong
   thing about it. They go through the same chain as everything else, so if
   Brevo is the thing that has broken, the backup mailbox carries the news.
   -------------------------------------------------------------------------- */

function gb_outbox_queue_alert( $message ) {
    $GLOBALS['gb_outbox_alerts'][] = sanitize_text_field( (string) $message );
}

function gb_outbox_alert_recipients() {
    $to = array();

    if ( function_exists( 'gb_get_support_email' ) && is_email( gb_get_support_email() ) ) {
        $to[] = gb_get_support_email();
    }

    $admin = get_option( 'admin_email' );
    if ( is_email( $admin ) && ! in_array( strtolower( $admin ), array_map( 'strtolower', $to ), true ) ) {
        $to[] = $admin;
    }

    return $to;
}

add_action( 'shutdown', function () {
    if ( empty( $GLOBALS['gb_outbox_alerts'] ) ) {
        return;
    }

    $lines = array_unique( (array) $GLOBALS['gb_outbox_alerts'] );
    unset( $GLOBALS['gb_outbox_alerts'] );

    // At most one every few hours: a flood of alerts is its own outage.
    if ( get_transient( 'gb_outbox_alert_sent' ) ) {
        return;
    }

    $to = gb_outbox_alert_recipients();
    if ( ! $to ) {
        return;
    }

    set_transient( 'gb_outbox_alert_sent', 1, 3 * HOUR_IN_SECONDS );

    // Nobody should wait on the page while this goes out.
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    } elseif ( function_exists( 'litespeed_finish_request' ) ) {
        litespeed_finish_request();
    }

    $settings_url = function_exists( 'gb_get_manager_settings_url' )
        ? gb_get_manager_settings_url( array( 'section' => 'email' ) )
        : home_url( '/' );

    $GLOBALS['gb_outbox_kind'] = 'alert';
    wp_mail(
        $to,
        'The Green Broom: email delivery needs a look',
        implode( "\n\n", array_merge(
            array( 'Something is wrong with how the site sends email.' ),
            $lines,
            array(
                'Emails that failed are being retried automatically, and anything that could not be delivered can be resent by hand.',
                'Details and the resend button: ' . $settings_url,
            )
        ) ),
        array( 'Content-Type: text/plain; charset=UTF-8' )
    );
}, 100 ); // After the queue runner, so anything it gives up on is included.

function gb_outbox_route_label( $route ) {
    $labels = array(
        'brevo'       => 'Brevo',
        'smtp_backup' => 'the backup mailbox',
        'php_mail'    => 'the web server',
    );

    return isset( $labels[ $route ] ) ? $labels[ $route ] : (string) $route;
}
