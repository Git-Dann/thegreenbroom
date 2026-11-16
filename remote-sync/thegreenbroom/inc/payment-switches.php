<?php
/**
 * Payment switches: three on/off controls in Settings, Stripe credentials.
 *
 *   Pause online bookings    nobody can book or pay for a clean; listings
 *                            and profiles stay up and say so.
 *   Pause new memberships    no new customer memberships or cleaner listings
 *                            can be bought; existing ones carry on, and can
 *                            still be managed or cancelled.
 *   Automatic refunds        on (the default): a cancellation refunds what
 *                            the policy says, straight away. Off: the refund
 *                            due is worked out and recorded, the team is told,
 *                            and somebody sends it by hand.
 *
 * Each is a single flag on the settings page, saved by the generic _gb_ meta
 * saver in page-manager-dashboard.php like every other setting. They are read
 * where the thing they switch off is decided, so the buttons, the pages and
 * the final server-side checks all agree.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_payment_switch_raw( $key ) {
    $settings_id = (int) get_option( 'page_on_front' );

    return $settings_id ? (string) get_post_meta( $settings_id, $key, true ) : '';
}

function gb_bookings_paused() {
    return gb_payment_switch_raw( '_gb_pause_bookings' ) === '1';
}

function gb_memberships_paused() {
    return gb_payment_switch_raw( '_gb_pause_memberships' ) === '1';
}

/**
 * On unless it has been switched off, so a site that has never saved the
 * setting keeps the behaviour it always had.
 */
function gb_auto_refunds_enabled() {
    return gb_payment_switch_raw( '_gb_auto_refunds' ) !== '0';
}

function gb_bookings_paused_message() {
    return 'Online booking is paused for a short while. Please check back soon, or get in touch and we will help.';
}
