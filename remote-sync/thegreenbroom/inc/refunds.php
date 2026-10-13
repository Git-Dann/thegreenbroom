<?php
/**
 * Booking refunds and the cancellation policy
 *
 * The client's rule: "Refunds are partial or full depending on when the
 * customer cancels." Until this file existed a cancellation only changed a
 * field, and every paid cancellation became an email asking somebody to go
 * into Stripe and work out by hand what was owed.
 *
 * The policy, from Manager OS > Settings > Safeguarding:
 *
 *   Cancelled by the customer more than the window before the clean  full refund
 *   Cancelled by the customer inside the window                       the set percentage
 *   Cancelled by the customer once the clean has started              no refund
 *   Cancelled by the cleaner or the team (Manager OS)                 full refund
 *
 * Bookings are destination charges: the customer pays the platform, Stripe
 * moves the money on to the cleaner's connected account, and the platform
 * keeps an application fee (currently 0%). A refund therefore has to pull
 * the money back from the cleaner as well, or the platform pays every refund
 * out of its own balance while the cleaner keeps the lot. See
 * gb_refund_booking() for how that is done.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   Settings
   ========================================================================== */

/**
 * The two numbers the policy runs on.
 *
 * A blank setting means "never saved", so it gets the default. A saved 0 is
 * a real choice and is kept: a 0 hour window means a full refund right up to
 * the start, and 0% means nothing back inside the window.
 */
function gb_refund_policy_settings() {
    $settings_id = function_exists( 'gb_get_site_settings_id' ) ? gb_get_site_settings_id() : (int) get_option( 'page_on_front' );

    $window  = $settings_id ? trim( (string) get_post_meta( $settings_id, '_gb_cancel_window', true ) ) : '';
    $percent = $settings_id ? trim( (string) get_post_meta( $settings_id, '_gb_cancel_partial_percent', true ) ) : '';

    $window  = ( $window === '' || ! is_numeric( $window ) ) ? 48.0 : (float) $window;
    $percent = ( $percent === '' || ! is_numeric( $percent ) ) ? 50.0 : (float) $percent;

    return [
        'window_hours'    => max( 0, $window ),
        'partial_percent' => min( 100, max( 0, $percent ) ),
    ];
}

/* ==========================================================================
   Small formatting helpers
   ========================================================================== */

function gb_refund_money( $pence ) {
    return '£' . number_format( max( 0, (int) $pence ) / 100, 2 );
}

function gb_refund_gbp_string( $pence ) {
    return number_format( max( 0, (int) $pence ) / 100, 2, '.', '' );
}

function gb_refund_gbp_to_pence( $gbp ) {
    return (int) round( (float) $gbp * 100 );
}

function gb_refund_hours_label( $hours ) {
    $hours = (float) $hours;
    $text  = ( floor( $hours ) == $hours ) ? (string) (int) $hours : rtrim( rtrim( number_format( $hours, 2, '.', '' ), '0' ), '.' );

    return $text . ' hour' . ( $hours == 1 ? '' : 's' );
}

function gb_refund_percent_label( $percent ) {
    $percent = (float) $percent;
    $text    = ( floor( $percent ) == $percent ) ? (string) (int) $percent : rtrim( rtrim( number_format( $percent, 2, '.', '' ), '0' ), '.' );

    return $text . '%';
}

function gb_refund_status_label( $status ) {
    $labels = [
        'refunded'           => 'Refunded in full',
        'partially_refunded' => 'Partly refunded',
        'failed'             => 'Refund failed',
    ];

    return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
}

/* ==========================================================================
   What was paid, and what has already gone back
   ========================================================================== */

/**
 * How much the customer paid for a booking and how much has been refunded.
 *
 * Stripe is asked first when $check_stripe is true, because it is the only
 * record that knows about a refund somebody made by hand in the Stripe
 * dashboard before the webhook reached us. If Stripe cannot be reached (or
 * there are no keys, as on a local copy) the booking's own meta is used.
 *
 * Cached for the request, and forgotten whenever a refund is made or
 * recorded, so one page view asks Stripe once per booking at most.
 */
function gb_booking_payment_state( $booking_id, $check_stripe = true, $refresh = false ) {
    $booking_id = absint( $booking_id );
    $key        = $booking_id . ( $check_stripe ? ':stripe' : ':local' );

    if ( $refresh ) {
        gb_booking_payment_state_forget( $booking_id );
    }
    if ( isset( $GLOBALS['gb_refund_state_cache'][ $key ] ) ) {
        return $GLOBALS['gb_refund_state_cache'][ $key ];
    }

    $state = [
        'paid_pence'     => gb_refund_gbp_to_pence( get_post_meta( $booking_id, '_gb_booking_total_gbp', true ) ),
        'refunded_pence' => gb_refund_gbp_to_pence( get_post_meta( $booking_id, '_gb_refund_amount_gbp', true ) ),
        'payment_intent' => sanitize_text_field( (string) get_post_meta( $booking_id, '_gb_stripe_payment_intent', true ) ),
        'destination'    => sanitize_text_field( (string) get_post_meta( $booking_id, '_gb_stripe_destination_account', true ) ),
        'fee_pence'      => gb_refund_gbp_to_pence( get_post_meta( $booking_id, '_gb_platform_fee_gbp', true ) ),
        'source'         => 'local',
        'stripe_error'   => null,
    ];

    if ( $check_stripe && $state['payment_intent'] !== '' && function_exists( 'gb_stripe_api_request' ) ) {
        $pi = gb_stripe_api_request(
            'payment_intents/' . rawurlencode( $state['payment_intent'] ),
            [ 'expand' => [ 'latest_charge' ] ],
            'GET'
        );

        if ( is_wp_error( $pi ) ) {
            $state['stripe_error'] = $pi;
        } elseif ( is_array( $pi ) ) {
            $state['source'] = 'stripe';

            if ( isset( $pi['amount_received'] ) && (int) $pi['amount_received'] > 0 ) {
                $state['paid_pence'] = (int) $pi['amount_received'];
            }
            $state['destination'] = isset( $pi['transfer_data']['destination'] ) && is_string( $pi['transfer_data']['destination'] )
                ? sanitize_text_field( $pi['transfer_data']['destination'] )
                : '';
            $state['fee_pence'] = isset( $pi['application_fee_amount'] ) ? (int) $pi['application_fee_amount'] : 0;

            if ( isset( $pi['latest_charge'] ) && is_array( $pi['latest_charge'] ) ) {
                $charge = $pi['latest_charge'];
                if ( isset( $charge['amount_refunded'] ) ) {
                    $state['refunded_pence'] = (int) $charge['amount_refunded'];
                }
                if ( ! empty( $charge['id'] ) && get_post_meta( $booking_id, '_gb_stripe_charge_id', true ) === '' ) {
                    update_post_meta( $booking_id, '_gb_stripe_charge_id', sanitize_text_field( $charge['id'] ) );
                }
            }
        }
    }

    $GLOBALS['gb_refund_state_cache'][ $key ] = $state;

    return $state;
}

function gb_booking_payment_state_forget( $booking_id ) {
    $booking_id = absint( $booking_id );
    unset( $GLOBALS['gb_refund_state_cache'][ $booking_id . ':stripe' ], $GLOBALS['gb_refund_state_cache'][ $booking_id . ':local' ] );
}

/* ==========================================================================
   The quote
   ========================================================================== */

/**
 * What a customer would get back if they cancelled this booking now.
 *
 * The base is what is left of the payment: what Stripe actually took, less
 * anything already refunded. The policy percentage is applied to that.
 *
 * @param int      $booking_id
 * @param int|null $now          Unix time to quote for (tests); defaults to now.
 * @param bool     $check_stripe Ask Stripe for the paid and refunded amounts.
 * @return array amount_pence, percent, reason ('full', 'partial', 'none',
 *               'not_paid', 'already_refunded') and the figures behind them.
 */
function gb_booking_refund_quote( $booking_id, $now = null, $check_stripe = true ) {
    $booking_id = absint( $booking_id );
    $now        = $now === null ? time() : (int) $now;
    $policy     = gb_refund_policy_settings();

    $quote = [
        'amount_pence'    => 0,
        'percent'         => 0,
        'reason'          => 'not_paid',
        'paid_pence'      => 0,
        'refunded_pence'  => 0,
        'remaining_pence' => 0,
        'starts_at'       => 0,
        'hours_before'    => null,
        'started'         => false,
        'window_hours'    => $policy['window_hours'],
        'partial_percent' => $policy['partial_percent'],
        'source'          => 'local',
    ];

    if ( ! $booking_id || get_post_type( $booking_id ) !== 'gb_booking' ) {
        return $quote;
    }

    $date = (string) get_post_meta( $booking_id, '_gb_booking_date', true );
    $time = (string) get_post_meta( $booking_id, '_gb_booking_time', true );
    if ( $date !== '' ) {
        $quote['starts_at'] = function_exists( 'gb_booking_moment' ) ? (int) gb_booking_moment( $date, $time ) : (int) strtotime( $date . ' ' . $time );
    }
    if ( $quote['starts_at'] > 0 ) {
        $quote['hours_before'] = ( $quote['starts_at'] - $now ) / HOUR_IN_SECONDS;
        $quote['started']      = $now >= $quote['starts_at'];
    }

    $paid = get_post_meta( $booking_id, '_gb_payment_status', true ) === 'paid'
        && get_post_meta( $booking_id, '_gb_stripe_payment_intent', true ) !== '';
    if ( ! $paid ) {
        return $quote;
    }

    $state = gb_booking_payment_state( $booking_id, $check_stripe );

    $quote['source']          = $state['source'];
    $quote['paid_pence']      = $state['paid_pence'];
    $quote['refunded_pence']  = min( $state['paid_pence'], $state['refunded_pence'] );
    $quote['remaining_pence'] = max( 0, $state['paid_pence'] - $state['refunded_pence'] );

    if ( $quote['remaining_pence'] <= 0 ) {
        $quote['reason'] = 'already_refunded';
        return $quote;
    }

    if ( $quote['started'] ) {
        $quote['reason'] = 'none';
        return $quote;
    }

    // No date on the booking means it cannot have started; treat it as
    // comfortably outside the window rather than keep the customer's money.
    $outside_window = $quote['hours_before'] === null || $quote['hours_before'] > $policy['window_hours'];

    if ( $outside_window || $policy['partial_percent'] >= 100 ) {
        $quote['percent']      = 100;
        $quote['amount_pence'] = $quote['remaining_pence'];
        $quote['reason']       = 'full';
        return $quote;
    }

    $quote['percent']      = $policy['partial_percent'];
    $quote['amount_pence'] = min( $quote['remaining_pence'], (int) round( $quote['remaining_pence'] * $policy['partial_percent'] / 100 ) );
    $quote['reason']       = $quote['amount_pence'] > 0 ? 'partial' : 'none';

    return $quote;
}

/**
 * The quote in one plain sentence for the customer, shown next to the
 * Cancel button and in the confirm box. Empty when nothing was paid, as
 * there is nothing useful to say about money.
 */
function gb_refund_quote_summary( $quote ) {
    if ( ! is_array( $quote ) ) {
        return '';
    }

    $window = gb_refund_hours_label( $quote['window_hours'] );

    switch ( $quote['reason'] ) {
        case 'full':
            return 'Cancel now and get ' . gb_refund_money( $quote['amount_pence'] ) . ' back (full refund).';
        case 'partial':
            return 'Cancel now and get ' . gb_refund_money( $quote['amount_pence'] ) . ' back ('
                . gb_refund_percent_label( $quote['percent'] ) . ', as it is within ' . $window . ' of the clean).';
        case 'none':
            return $quote['started']
                ? 'Cancelling now gets no refund, as the clean has started.'
                : 'Cancelling now gets no refund, as it is within ' . $window . ' of the clean.';
        case 'already_refunded':
            return 'This booking has already been refunded.';
    }

    return '';
}

/**
 * The same quote, said after the event, for emails and the refund log:
 * "50%, as it was cancelled within 48 hours of the clean".
 */
function gb_refund_quote_reason_text( $quote ) {
    if ( ! is_array( $quote ) ) {
        return '';
    }

    $window = gb_refund_hours_label( $quote['window_hours'] );

    switch ( $quote['reason'] ) {
        case 'full':
            return 'full refund, as it was cancelled more than ' . $window . ' before the clean';
        case 'partial':
            return gb_refund_percent_label( $quote['percent'] ) . ', as it was cancelled within ' . $window . ' of the clean';
        case 'none':
            return $quote['started']
                ? 'no refund, as the clean had already started'
                : 'no refund, as it was cancelled within ' . $window . ' of the clean';
        case 'already_refunded':
            return 'it had already been refunded';
    }

    return 'nothing was paid';
}

/** One sentence for the customer's refund email. */
function gb_refund_customer_why( $quote ) {
    if ( ! is_array( $quote ) ) {
        return '';
    }

    $window = gb_refund_hours_label( $quote['window_hours'] );

    if ( $quote['reason'] === 'full' ) {
        return 'You cancelled more than ' . $window . ' before the clean, so you get a full refund.';
    }
    if ( $quote['reason'] === 'partial' ) {
        return 'You cancelled within ' . $window . ' of the clean, so under our cancellation policy you get '
            . gb_refund_percent_label( $quote['percent'] ) . ' of what you paid back.';
    }

    return '';
}

/* ==========================================================================
   Issuing a refund
   ========================================================================== */

/**
 * Refund part or all of a booking's payment through Stripe.
 *
 * reverse_transfer=true pulls the cleaner's share back from their connected
 * account in proportion to the refund, and refund_application_fee=true gives
 * back the platform's fee in the same proportion. So a 50% refund of a
 * £100 booking with a £10 fee takes £45 back from the cleaner and £5 from the
 * platform, and both sides end up with half of what they had. Each flag is
 * only sent when the charge has that part (a transfer, a fee above zero):
 * an "any professional" booking has no transfer, and the fee is currently
 * 0%, and there is nothing to be gained from asking Stripe to reverse
 * something that does not exist. Stripe's own processing fee is not
 * returned to the platform on a refund; that is Stripe's rule, not ours.
 *
 * Idempotency: the key is built from the booking, how much had already been
 * refunded, and the amount. A double click, a retry or two tabs all build
 * the same key, so Stripe hands back the first refund instead of making a
 * second. A later, genuinely different refund (a manager topping up a 50%
 * refund to the full amount) starts from a different refunded total, so it
 * gets a new key and is not mistaken for the first one.
 *
 * Mode: payment intent ids belong to one Stripe mode. A booking paid in
 * test mode cannot be refunded with live keys, and trying would only ever
 * come back "No such payment_intent". The mode is recorded at checkout
 * (_gb_stripe_mode) and checked here first; older bookings without it rely
 * on Stripe's answer, which is recorded as a failure, never a crash.
 *
 * @return array|WP_Error refund_id, amount_pence, total_refunded_pence, status
 */
function gb_refund_booking( $booking_id, $amount_pence, $reason_note = '' ) {
    $booking_id   = absint( $booking_id );
    $amount_pence = (int) $amount_pence;
    $reason_note  = sanitize_text_field( (string) $reason_note );

    if ( ! $booking_id || get_post_type( $booking_id ) !== 'gb_booking' ) {
        return new WP_Error( 'gb_refund_invalid_booking', 'That booking could not be found.' );
    }
    if ( $amount_pence <= 0 ) {
        return new WP_Error( 'gb_refund_nothing_due', 'There is nothing to refund.' );
    }

    $payment_intent = sanitize_text_field( (string) get_post_meta( $booking_id, '_gb_stripe_payment_intent', true ) );
    if ( get_post_meta( $booking_id, '_gb_payment_status', true ) !== 'paid' || $payment_intent === '' ) {
        return gb_refund_record_failure( $booking_id, $amount_pence, 'This booking has no Stripe payment to refund.', $reason_note );
    }

    $paid_mode = (string) get_post_meta( $booking_id, '_gb_stripe_mode', true );
    $site_mode = function_exists( 'gb_get_stripe_config' ) ? (string) gb_get_stripe_config()['mode'] : '';
    if ( $paid_mode !== '' && $site_mode !== '' && $paid_mode !== $site_mode ) {
        return gb_refund_record_failure(
            $booking_id,
            $amount_pence,
            sprintf( 'This booking was paid in Stripe %s mode, but the site is now in %s mode. Refund it from the Stripe dashboard in %s mode.', $paid_mode, $site_mode, $paid_mode ),
            $reason_note
        );
    }

    $state = gb_booking_payment_state( $booking_id, true, true );

    if ( is_wp_error( $state['stripe_error'] ) ) {
        $data = $state['stripe_error']->get_error_data();
        if ( is_array( $data ) && ( (int) ( $data['status'] ?? 0 ) === 404 || ( $data['payload']['error']['code'] ?? '' ) === 'resource_missing' ) ) {
            return gb_refund_record_failure(
                $booking_id,
                $amount_pence,
                'Stripe has no record of this payment in ' . ( $site_mode ?: 'the current' ) . ' mode (' . $state['stripe_error']->get_error_message() . '). It was probably taken in the other mode.',
                $reason_note
            );
        }
    }

    $remaining = max( 0, $state['paid_pence'] - $state['refunded_pence'] );
    if ( $remaining <= 0 ) {
        return new WP_Error( 'gb_refund_nothing_due', 'This booking has already been refunded in full.' );
    }
    $amount_pence = min( $amount_pence, $remaining );

    /*
     * Only things that follow from the key go in the request. Stripe refuses
     * a reused key whose parameters differ, so a free-text note here would
     * turn a harmless retry from a different screen into an error. The note
     * is kept in the booking's own refund log instead.
     */
    $params = [
        'payment_intent'       => $payment_intent,
        'amount'               => $amount_pence,
        'metadata[booking_id]' => (string) $booking_id,
    ];
    if ( $state['destination'] !== '' ) {
        $params['reverse_transfer'] = 'true';
    }
    if ( $state['fee_pence'] > 0 ) {
        $params['refund_application_fee'] = 'true';
    }

    $idempotency_key = 'gb-refund-' . $booking_id . '-' . (int) $state['refunded_pence'] . '-' . $amount_pence;

    $refund = gb_stripe_api_request( 'refunds', $params, 'POST', [ 'Idempotency-Key' => $idempotency_key ] );

    if ( is_wp_error( $refund ) ) {
        return gb_refund_record_failure( $booking_id, $amount_pence, $refund->get_error_message(), $reason_note );
    }

    $refund_status = isset( $refund['status'] ) ? sanitize_key( (string) $refund['status'] ) : '';
    if ( empty( $refund['id'] ) || in_array( $refund_status, [ 'failed', 'canceled' ], true ) ) {
        $why = ! empty( $refund['failure_reason'] ) ? ' (' . sanitize_text_field( (string) $refund['failure_reason'] ) . ')' : '';
        return gb_refund_record_failure( $booking_id, $amount_pence, 'Stripe did not complete the refund' . $why . '.', $reason_note );
    }

    $refunded_now = isset( $refund['amount'] ) ? (int) $refund['amount'] : $amount_pence;
    $total        = min( $state['paid_pence'], (int) $state['refunded_pence'] + $refunded_now );
    $status       = $total >= $state['paid_pence'] ? 'refunded' : 'partially_refunded';

    update_post_meta( $booking_id, '_gb_refund_status', $status );
    update_post_meta( $booking_id, '_gb_refund_amount_gbp', gb_refund_gbp_string( $total ) );
    update_post_meta( $booking_id, '_gb_refund_id', sanitize_text_field( (string) $refund['id'] ) );
    update_post_meta( $booking_id, '_gb_refund_at', current_time( 'mysql' ) );
    delete_post_meta( $booking_id, '_gb_refund_error' );
    gb_refund_log( $booking_id, [
        'amount' => gb_refund_gbp_string( $refunded_now ),
        'id'     => (string) $refund['id'],
        'status' => $refund_status,
        'note'   => $reason_note,
    ] );

    gb_booking_payment_state_forget( $booking_id );

    return [
        'refund_id'           => (string) $refund['id'],
        'amount_pence'         => $refunded_now,
        'total_refunded_pence' => $total,
        'status'               => $status,
    ];
}

/**
 * A failed refund. The status says so and the error is kept word for word,
 * so whoever opens the booking in Manager OS can see exactly what Stripe
 * said. Anything already refunded stays recorded as it was.
 */
function gb_refund_record_failure( $booking_id, $amount_pence, $message, $reason_note = '' ) {
    $message = sanitize_text_field( (string) $message );

    update_post_meta( $booking_id, '_gb_refund_status', 'failed' );
    update_post_meta( $booking_id, '_gb_refund_error', $message );
    update_post_meta( $booking_id, '_gb_refund_failed_at', current_time( 'mysql' ) );
    gb_refund_log( $booking_id, [
        'amount' => gb_refund_gbp_string( $amount_pence ),
        'status' => 'failed',
        'error'  => $message,
        'note'   => $reason_note,
    ] );

    return new WP_Error( 'gb_refund_failed', $message );
}

/** A short history of refund attempts on the booking, newest last. */
function gb_refund_log( $booking_id, $entry ) {
    $log = get_post_meta( $booking_id, '_gb_refund_log', true );
    $log = is_array( $log ) ? $log : [];

    $entry['at'] = current_time( 'mysql' );
    $log[]       = array_map( 'sanitize_text_field', array_map( 'strval', $entry ) );

    update_post_meta( $booking_id, '_gb_refund_log', array_slice( $log, -20 ) );
}

/* ==========================================================================
   Emails
   ========================================================================== */

function gb_refund_booking_when( $booking_id ) {
    $service = (string) get_post_meta( $booking_id, '_gb_booking_service', true );
    $date    = (string) get_post_meta( $booking_id, '_gb_booking_date', true );
    $time    = (string) get_post_meta( $booking_id, '_gb_booking_time', true );

    $date_fmt = $date !== '' && strtotime( $date ) ? date_i18n( 'l j F Y', strtotime( $date ) ) : $date;
    $time_fmt = $time !== '' && strtotime( $time ) ? date_i18n( 'g:i a', strtotime( $time ) ) : $time;

    return trim( $service . ' on ' . $date_fmt . ( $time_fmt !== '' ? ' at ' . $time_fmt : '' ) );
}

/**
 * Tell the customer what has gone back to their card.
 *
 * @param string $why One plain sentence on why this amount, or ''.
 */
function gb_refund_email_customer( $booking_id, $amount_pence, $why = '' ) {
    $email = sanitize_email( (string) get_post_meta( $booking_id, '_gb_customer_email', true ) );
    if ( ! is_email( $email ) || (int) $amount_pence <= 0 ) {
        return;
    }

    $name = (string) get_post_meta( $booking_id, '_gb_customer_name', true );
    $first = function_exists( 'gb_brevo_first_name' ) ? gb_brevo_first_name( $name ) : ( $name !== '' ? $name : 'there' );

    wp_mail(
        $email,
        'Your refund for booking #' . $booking_id,
        "Hi {$first},\n\n"
        . 'We have refunded ' . gb_refund_money( $amount_pence ) . ' to the card you paid with, for your booking:' . "\n\n"
        . gb_refund_booking_when( $booking_id ) . "\n\n"
        . ( $why !== '' ? $why . "\n\n" : '' )
        . "Refunds usually take 5 to 10 working days to show on your statement.\n\n"
        . "If you have any questions, just reply to this email.\n\n"
        . 'The Green Broom'
    );
}

/** Tell the team a refund did not go through, with Stripe's own words. */
function gb_refund_email_support_failure( $booking_id, $amount_pence, $error, $who ) {
    if ( ! function_exists( 'gb_get_support_email' ) || ! is_email( gb_get_support_email() ) ) {
        return;
    }

    wp_mail(
        gb_get_support_email(),
        'Booking #' . $booking_id . ' cancelled - REFUND FAILED, action needed',
        "Booking #{$booking_id} (" . gb_refund_booking_when( $booking_id ) . ") was cancelled by {$who}.\n\n"
        . 'A refund of ' . gb_refund_money( $amount_pence ) . " was due but Stripe did not accept it:\n\n"
        . $error . "\n\n"
        . "Please refund it by hand in the Stripe dashboard, or use \"Refund the rest in full\" on the booking once the problem is fixed.\n\n"
        . 'Open it in Manager OS: ' . home_url( '/manager-dashboard/?tab=bookings&action=edit&id=' . $booking_id )
    );
}

/* ==========================================================================
   Cancellations by the cleaner or the team: always a full refund
   ========================================================================== */

/**
 * Refund whatever is left of a booking the team has cancelled.
 *
 * Manager OS cancels a booking by moving it to the bin and setting
 * _gb_booking_status to cancelled, in that order, in one save. Both are
 * hooked below so that any future route that does either is covered too;
 * the static list makes the second one a no-op in the same request, and
 * the quote's "what is left" makes a repeat save on another day a no-op.
 *
 * Not refunded here:
 *  - customer cancellations (_gb_cancelled_by = customer). page-my-account.php
 *    has already applied the policy and refunded what was due, and a
 *    manager later re-saving that booking must not quietly top it up to
 *    100%. If they mean to, the "Refund the rest in full" button says so.
 *  - bookings whose clean time has already passed. Moving an old, finished
 *    booking to the bin is housekeeping, not a cancellation, and should
 *    never hand back money for a clean that happened. A no-show is the one
 *    real case, and the button covers it.
 */
function gb_refund_on_team_cancellation( $booking_id ) {
    static $handled = [];

    $booking_id = absint( $booking_id );
    if ( ! $booking_id || isset( $handled[ $booking_id ] ) || get_post_type( $booking_id ) !== 'gb_booking' ) {
        return;
    }

    if ( get_post_meta( $booking_id, '_gb_cancelled_by', true ) === 'customer' ) {
        return;
    }
    $handled[ $booking_id ] = true;

    if ( get_post_meta( $booking_id, '_gb_cancelled_by', true ) === '' ) {
        update_post_meta( $booking_id, '_gb_cancelled_by', 'team' );
        update_post_meta( $booking_id, '_gb_cancelled_at', current_time( 'mysql' ) );
    }

    $quote = gb_booking_refund_quote( $booking_id );
    if ( in_array( $quote['reason'], [ 'not_paid', 'already_refunded' ], true ) || $quote['remaining_pence'] <= 0 ) {
        return;
    }

    if ( $quote['started'] ) {
        update_post_meta(
            $booking_id,
            '_gb_refund_note',
            'Cancelled after the clean time had passed, so nothing was refunded automatically. Use "Refund the rest in full" if the customer is owed it.'
        );
        return;
    }

    $result = gb_refund_booking( $booking_id, $quote['remaining_pence'], 'Cancelled by the team (full refund)' );

    if ( is_wp_error( $result ) ) {
        gb_refund_email_support_failure( $booking_id, $quote['remaining_pence'], $result->get_error_message(), 'the team in Manager OS' );
        return;
    }

    delete_post_meta( $booking_id, '_gb_refund_note' );
    gb_refund_email_customer(
        $booking_id,
        $result['amount_pence'],
        'Your booking has been cancelled by us, so you have been refunded in full. We are sorry for the trouble.'
    );
}

add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( $post instanceof WP_Post && $post->post_type === 'gb_booking' && $new_status === 'trash' && $old_status !== 'trash' ) {
        gb_refund_on_team_cancellation( $post->ID );
    }
}, 20, 3 );

/*
 * The status field, for anything that cancels by meta alone. A booking put
 * back to confirmed or pending loses its "cancelled by" mark, so that if the
 * team cancel it later it is treated as their cancellation, not the
 * customer's earlier one.
 */
function gb_refund_watch_booking_status( $meta_id, $object_id, $meta_key, $meta_value ) {
    if ( $meta_key !== '_gb_booking_status' || get_post_type( $object_id ) !== 'gb_booking' ) {
        return;
    }

    if ( $meta_value === 'cancelled' ) {
        gb_refund_on_team_cancellation( $object_id );
    } elseif ( in_array( $meta_value, [ 'confirmed', 'pending_payment' ], true ) ) {
        delete_post_meta( $object_id, '_gb_cancelled_by' );
    }
}
add_action( 'added_post_meta', 'gb_refund_watch_booking_status', 20, 4 );
add_action( 'updated_post_meta', 'gb_refund_watch_booking_status', 20, 4 );

/* ==========================================================================
   "Refund the rest in full" from the Manager OS booking editor
   ========================================================================== */

function gb_refund_user_is_manager() {
    $user = wp_get_current_user();

    return $user && $user->ID && ( current_user_can( 'administrator' ) || in_array( 'gb_manager', (array) $user->roles, true ) );
}

function gb_refund_handle_manager_button() {
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || empty( $_POST['gb_refund_action'] ) || $_POST['gb_refund_action'] !== 'refund_remaining' ) {
        return;
    }

    $booking_id = isset( $_POST['gb_refund_booking_id'] ) ? absint( $_POST['gb_refund_booking_id'] ) : 0;
    $back       = home_url( '/manager-dashboard/?tab=bookings&action=edit&id=' . $booking_id );

    if ( ! gb_refund_user_is_manager() ) {
        wp_die( 'You do not have permission to refund bookings.' );
    }
    if ( empty( $_POST['gb_refund_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gb_refund_nonce'] ) ), 'gb_refund_remaining_' . $booking_id ) ) {
        wp_safe_redirect( add_query_arg( 'gb_refund', 'expired', $back ) );
        exit;
    }

    $quote = gb_booking_refund_quote( $booking_id );
    if ( $quote['remaining_pence'] <= 0 || $quote['reason'] === 'not_paid' ) {
        wp_safe_redirect( add_query_arg( 'gb_refund', 'nothing', $back ) );
        exit;
    }

    $result = gb_refund_booking( $booking_id, $quote['remaining_pence'], 'Refunded in full by the team from Manager OS' );
    if ( is_wp_error( $result ) ) {
        wp_safe_redirect( add_query_arg( 'gb_refund', 'failed', $back ) );
        exit;
    }

    delete_post_meta( $booking_id, '_gb_refund_note' );
    gb_refund_email_customer( $booking_id, $result['amount_pence'], '' );

    wp_safe_redirect( add_query_arg( 'gb_refund', 'done', $back ) );
    exit;
}
add_action( 'init', 'gb_refund_handle_manager_button', 20 );

/* ==========================================================================
   Stripe webhook: refunds made anywhere, including by hand in Stripe
   ========================================================================== */

function gb_refund_find_booking( $charge_id = '', $payment_intent = '', $metadata_booking_id = 0 ) {
    foreach ( [ '_gb_stripe_charge_id' => $charge_id, '_gb_stripe_payment_intent' => $payment_intent ] as $key => $value ) {
        $value = sanitize_text_field( (string) $value );
        if ( $value === '' ) {
            continue;
        }
        // Listed out because 'any' leaves out the bin, which is where
        // bookings the team cancelled live.
        $found = get_posts( [
            'post_type'      => 'gb_booking',
            'post_status'    => [ 'publish', 'pending', 'draft', 'private', 'future', 'trash' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => $key,
            'meta_value'     => $value,
            'no_found_rows'  => true,
        ] );
        if ( ! empty( $found ) ) {
            return (int) $found[0];
        }
    }

    // Metadata alone is trusted only when it agrees with the booking.
    $metadata_booking_id = absint( $metadata_booking_id );
    if ( $metadata_booking_id && get_post_type( $metadata_booking_id ) === 'gb_booking'
        && $payment_intent !== '' && get_post_meta( $metadata_booking_id, '_gb_stripe_payment_intent', true ) === $payment_intent ) {
        return $metadata_booking_id;
    }

    return 0;
}

/** Bring a booking's refund meta in line with a Stripe charge object. */
function gb_refund_sync_from_charge( $booking_id, $charge, $source_note = 'Stripe' ) {
    if ( ! is_array( $charge ) || ! isset( $charge['amount_refunded'] ) ) {
        return;
    }

    $refunded = (int) $charge['amount_refunded'];
    $amount   = isset( $charge['amount_captured'] ) && (int) $charge['amount_captured'] > 0 ? (int) $charge['amount_captured'] : (int) ( $charge['amount'] ?? 0 );
    if ( $refunded <= 0 ) {
        return;
    }

    $status = ( ! empty( $charge['refunded'] ) || ( $amount > 0 && $refunded >= $amount ) ) ? 'refunded' : 'partially_refunded';
    $before = gb_refund_gbp_to_pence( get_post_meta( $booking_id, '_gb_refund_amount_gbp', true ) );

    if ( ! empty( $charge['id'] ) && get_post_meta( $booking_id, '_gb_stripe_charge_id', true ) === '' ) {
        update_post_meta( $booking_id, '_gb_stripe_charge_id', sanitize_text_field( (string) $charge['id'] ) );
    }

    if ( $before === $refunded && get_post_meta( $booking_id, '_gb_refund_status', true ) === $status ) {
        return; // Already knew; usually our own refund coming back to us.
    }

    update_post_meta( $booking_id, '_gb_refund_status', $status );
    update_post_meta( $booking_id, '_gb_refund_amount_gbp', gb_refund_gbp_string( $refunded ) );
    update_post_meta( $booking_id, '_gb_refund_at', current_time( 'mysql' ) );
    delete_post_meta( $booking_id, '_gb_refund_error' );

    $latest = isset( $charge['refunds']['data'][0]['id'] ) ? (string) $charge['refunds']['data'][0]['id'] : '';
    if ( $latest !== '' ) {
        update_post_meta( $booking_id, '_gb_refund_id', sanitize_text_field( $latest ) );
    }

    gb_refund_log( $booking_id, [
        'amount' => gb_refund_gbp_string( max( 0, $refunded - $before ) ),
        'id'     => $latest,
        'status' => $status,
        'note'   => 'Recorded from ' . $source_note,
    ] );
}

/**
 * Called from gb_handle_stripe_webhook() for every v1 event. Picks out the
 * refund ones; never fails the webhook, because a refund we cannot match to
 * a booking (a membership, another site on the same test account) will not
 * match any better when Stripe retries it.
 */
function gb_refund_handle_stripe_event( $event_type, $object ) {
    $types = [ 'charge.refunded', 'charge.refund.updated', 'refund.created', 'refund.updated', 'refund.failed' ];
    if ( ! in_array( $event_type, $types, true ) || ! is_array( $object ) ) {
        return;
    }

    $is_charge = ( $object['object'] ?? '' ) === 'charge';
    $charge    = $is_charge ? $object : null;
    $charge_id = $is_charge ? (string) ( $object['id'] ?? '' ) : ( is_string( $object['charge'] ?? null ) ? $object['charge'] : '' );
    $pi        = is_string( $object['payment_intent'] ?? null ) ? $object['payment_intent'] : '';
    $meta_bid  = isset( $object['metadata']['booking_id'] ) ? absint( $object['metadata']['booking_id'] ) : 0;

    $booking_id = gb_refund_find_booking( $charge_id, $pi, $meta_bid );
    if ( ! $booking_id ) {
        return;
    }

    // A refund event carries one refund, not the running total, so ask
    // Stripe for the charge it belongs to and take the total from that.
    if ( ! $charge && $charge_id !== '' && function_exists( 'gb_stripe_api_request' ) ) {
        $fetched = gb_stripe_api_request( 'charges/' . rawurlencode( $charge_id ), [], 'GET' );
        if ( ! is_wp_error( $fetched ) ) {
            $charge = $fetched;
        }
    }

    if ( $charge ) {
        gb_refund_sync_from_charge( $booking_id, $charge, 'Stripe (' . $event_type . ')' );
    }

    $refund_status = ! $is_charge ? sanitize_key( (string) ( $object['status'] ?? '' ) ) : '';
    if ( in_array( $refund_status, [ 'failed', 'canceled' ], true ) ) {
        $why = ! empty( $object['failure_reason'] ) ? sanitize_text_field( (string) $object['failure_reason'] ) : $refund_status;
        gb_refund_record_failure(
            $booking_id,
            (int) ( $object['amount'] ?? 0 ),
            'Stripe could not complete refund ' . sanitize_text_field( (string) ( $object['id'] ?? '' ) ) . ' (' . $why . '). The customer has not had this money back; please arrange it another way.',
            'Stripe webhook'
        );
    }

    gb_booking_payment_state_forget( $booking_id );
}
