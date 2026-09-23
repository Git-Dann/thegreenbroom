<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * An append-only record of what was checked, by whom, and when.
 *
 * The documents themselves are deleted once a cleaner is approved, which is
 * the right thing to do - ID and criminal records data should not be kept
 * beyond its purpose. But deleting them left nothing to answer the question an
 * insurer, a customer's solicitor or the ICO would actually ask: on what basis
 * was this person verified?
 *
 * This log answers that without keeping any of the data. It records that a
 * document of a given type was received, its SHA-256 fingerprint, its size and
 * format, who approved it and when, and when it was destroyed. The fingerprint
 * is the useful part: it cannot be turned back into the document, but if a
 * cleaner ever produces their certificate again it can be checked against the
 * one that was actually seen.
 *
 * Nothing here is special category data: no images, no document numbers, no
 * names beyond the acting manager's.
 */
class GB_Verification_Log {

    const META_KEY = '_gb_verification_log';
    const MAX_ENTRIES = 500;

    /**
     * Append an event. Existing entries are never modified or removed.
     */
    public static function record( $cleaner_id, $event, $detail = array() ) {
        $cleaner_id = (int) $cleaner_id;
        if ( $cleaner_id < 1 || get_post_type( $cleaner_id ) !== 'gb_cleaner' ) {
            return;
        }

        $event = sanitize_key( (string) $event );
        if ( $event === '' ) {
            return;
        }

        $actor_id = get_current_user_id();
        $actor    = 'System';
        if ( $actor_id ) {
            $user  = get_userdata( $actor_id );
            // The name is stored as it was at the time: an audit record should
            // not change because somebody later edited their profile.
            $actor = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'User #' . $actor_id;
        }

        $entry = array(
            'event'    => $event,
            'at'       => current_time( 'mysql' ),
            'at_gmt'   => current_time( 'mysql', true ),
            'actor_id' => (int) $actor_id,
            'actor'    => $actor,
            'detail'   => array_map( 'sanitize_text_field', array_map( 'strval', (array) $detail ) ),
        );

        $log = get_post_meta( $cleaner_id, self::META_KEY, true );
        $log = is_array( $log ) ? $log : array();
        $log[] = $entry;

        if ( count( $log ) > self::MAX_ENTRIES ) {
            $log = array_slice( $log, -self::MAX_ENTRIES );
        }

        update_post_meta( $cleaner_id, self::META_KEY, $log );
    }

    public static function get( $cleaner_id ) {
        $log = get_post_meta( (int) $cleaner_id, self::META_KEY, true );

        return is_array( $log ) ? $log : array();
    }

    /**
     * A fingerprint of a file, taken before it is destroyed.
     */
    public static function fingerprint( $filepath ) {
        if ( ! is_readable( $filepath ) ) {
            return '';
        }

        $hash = @hash_file( 'sha256', $filepath );

        return $hash ? $hash : '';
    }

    /**
     * Plain-English labels, so the record reads the same to a manager and to
     * whoever asks to see it.
     */
    public static function describe( $event ) {
        $labels = array(
            'baseline'           => 'Baseline record (reconstructed)',
            'document_received'  => 'Document received',
            'document_destroyed' => 'Document destroyed',
            'check_passed'       => 'Check marked as passed',
            'check_cleared'      => 'Check marked as not passed',
            'verified'           => 'Cleaner marked as verified',
            'unverified'         => 'Verified status removed',
            'listing_published'  => 'Profile published',
            'listing_withdrawn'  => 'Profile withdrawn',
            'retention_erased'   => 'Trail erased under retention policy',
        );

        return isset( $labels[ $event ] ) ? $labels[ $event ] : ucfirst( str_replace( '_', ' ', $event ) );
    }
}
