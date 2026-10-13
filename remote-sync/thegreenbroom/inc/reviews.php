<?php
/**
 * Cleaner reviews: the public form, its spam defences and the approval queue
 *
 * The client's rule: "Write a review needs to go to admin for approval and be
 * anti spam, the admin can then approve."
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================================
   REVIEWS HELD FOR APPROVAL
   ==========================================================================
   Reviews are still WordPress comments on the gb_cleaner post with the star
   rating in the gb_rating comment meta, so the average, the profile list and
   the homepage testimonials (all in cleaner-profiles.php) carry on working
   unchanged.

   What changed is the way in. WordPress's own comment form stays closed on
   cleaner profiles (see gb_close_comments_on_directory_types() in
   functions.php): wp-comments-post.php is the first thing a spam bot tries,
   and it would publish or hold whatever it was given. Reviews now come
   through the form printed by gb_render_review_form() and the handler below,
   which:

     - always stores a review as pending (comment_approved = 0), so nothing
       reaches a profile until a manager presses Approve in Manager OS;
     - drops honeypot and too-fast submissions without storing anything;
     - limits each email address and each connection to a few a day;
     - drops exact repeats, and quarantines anything that scores as spam
       straight into the spam status, so it never clutters the queue;
     - allows one review per email address per cleaner;
     - strips links and markup from the text.

   Whatever happens, a visitor who passed basic validation is told the same
   thing: "Thanks, your review has been sent for approval". A bot learns
   nothing about which check it tripped.
   ========================================================================== */

const GB_REVIEW_MIN_LENGTH       = 20;
const GB_REVIEW_MAX_LENGTH       = 2000;
const GB_REVIEW_MIN_SECONDS      = 4;
const GB_REVIEW_LIMIT_PER_EMAIL  = 3;
const GB_REVIEW_LIMIT_PER_IP     = 5;

/**
 * Can the current user approve, reject and unpublish reviews?
 *
 * The same test the Manager OS page itself applies before it renders.
 */
function gb_user_can_moderate_reviews() {
    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) {
        return false;
    }

    return in_array( 'gb_manager', (array) $user->roles, true ) || current_user_can( 'administrator' );
}

/**
 * Reviews waiting for a manager, for the badge on the Manager OS menu.
 */
function gb_get_pending_review_count() {
    return (int) get_comments( array(
        'post_type' => 'gb_cleaner',
        'status'    => 'hold',
        'type'      => 'comment',
        'count'     => true,
    ) );
}

/**
 * The Manager OS Reviews tab, used in emails and redirects.
 */
function gb_get_reviews_tab_url( $args = array() ) {
    return add_query_arg( array_merge( array( 'tab' => 'reviews' ), $args ), home_url( '/manager-dashboard/' ) );
}

/**
 * Clean review text: no markup, no links, tidy whitespace.
 *
 * Links are removed rather than refused. A genuine reviewer who pastes one
 * loses only the link, and a link-dropping bot gains nothing by being
 * published (which it will not be anyway, as the raw text is scored first).
 */
function gb_clean_review_text( $text ) {
    $text = wp_strip_all_tags( (string) $text );
    $text = preg_replace( '#\b(?:https?://|www\.)\S+#i', '', $text );
    // Bare domains such as "cheap-pills.ru/offer".
    $text = preg_replace( '#\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:com|net|org|info|biz|ru|su|cn|tk|top|xyz|io|co|uk|co\.uk)\b(?:/\S*)?#i', '', $text );
    $text = sanitize_textarea_field( $text );
    // Collapse the gaps a removed link leaves behind, but keep paragraphs.
    $text = preg_replace( "/[ \t]{2,}/", ' ', $text );
    $text = preg_replace( "/\n{3,}/", "\n\n", $text );

    return trim( $text );
}

/**
 * The review a given email address has already left on a cleaner, if any.
 *
 * Only pending and approved reviews count. Spam and rejected ones are the
 * team's to deal with and should not block a genuine second attempt.
 *
 * @return WP_Comment|null
 */
function gb_find_existing_review( $cleaner_id, $email ) {
    $email = strtolower( trim( (string) $email ) );
    if ( $email === '' ) {
        return null;
    }

    foreach ( array( 'approve', 'hold' ) as $status ) {
        $found = get_comments( array(
            'post_id'      => (int) $cleaner_id,
            'author_email' => $email,
            'status'       => $status,
            'type'         => 'comment',
            'number'       => 1,
        ) );
        if ( ! empty( $found ) ) {
            return $found[0];
        }
    }

    return null;
}

/**
 * How many reviews this email address has sent in the last day, across every
 * cleaner and every outcome (including spam and rejected).
 *
 * This is the per-email limit. gb_rate_limit_check() keys every count on the
 * connection's IP as well, so on its own it would let somebody rotate
 * addresses or networks; counting the stored reviews does not.
 */
function gb_count_recent_reviews_by_email( $email ) {
    return (int) get_comments( array(
        'post_type'    => 'gb_cleaner',
        'author_email' => strtolower( trim( (string) $email ) ),
        'status'       => array( 'hold', 'approve', 'spam', 'trash' ),
        'type'         => 'comment',
        'date_query'   => array( array( 'after' => '24 hours ago', 'column' => 'comment_date_gmt' ) ),
        'count'        => true,
    ) );
}

/**
 * Text submitted in the last week under a DIFFERENT email address.
 *
 * gb_message_is_duplicate() catches the same person sending the same thing
 * twice. A bot rotating addresses sends the same thing under many names; a
 * real reviewer of at least 20 characters essentially never matches somebody
 * else word for word.
 */
function gb_review_text_seen_elsewhere( $email, $text ) {
    $key   = 'gb_rvtext_' . md5( strtolower( preg_replace( '/\s+/', ' ', trim( (string) $text ) ) ) );
    $email = strtolower( trim( (string) $email ) );
    $seen  = get_transient( $key );

    if ( ! is_string( $seen ) || $seen === '' ) {
        set_transient( $key, $email, WEEK_IN_SECONDS );
        return false;
    }

    return $seen !== $email;
}

/**
 * Messages for the ?review= flag the handler redirects back with.
 *
 * Only for mistakes a genuine visitor can fix. Anything a spam check decided
 * comes back as "sent", same as a real review.
 */
function gb_review_notice_messages() {
    return array(
        'sent'    => 'Thanks, your review has been sent for approval. It will appear here once our team has checked it.',
        'expired' => 'That page had been open a while, so your review was not sent. Please write it again and press Send.',
        'rating'  => 'Please choose a star rating from 1 to 5.',
        'name'    => 'Please add your name.',
        'email'   => 'Please add a valid email address. We never show it on the site.',
        'short'   => sprintf( 'Your review needs at least %d characters. A sentence or two about the clean is perfect.', GB_REVIEW_MIN_LENGTH ),
        'long'    => sprintf( 'Your review is a little long. Please keep it under %d characters.', GB_REVIEW_MAX_LENGTH ),
        'own'     => 'You cannot review your own profile.',
        'already' => 'You have already reviewed this cleaner, thank you. If you would like to change it, please email us at %s.',
    );
}

/* --------------------------------------------------------------------------
   PUBLIC SUBMISSION HANDLER
   --------------------------------------------------------------------------
   A plain POST to the profile page, handled on init and answered with a
   redirect back to the profile (post/redirect/get), so refreshing the thank
   you message cannot send the review twice.
   -------------------------------------------------------------------------- */
add_action( 'init', 'gb_handle_review_submission' );
function gb_handle_review_submission() {
    if ( ! isset( $_POST['gb_review_action'] ) || $_POST['gb_review_action'] !== 'submit_review' ) {
        return;
    }

    $cleaner_id = isset( $_POST['gb_review_cleaner'] ) ? absint( $_POST['gb_review_cleaner'] ) : 0;
    if ( ! $cleaner_id || get_post_type( $cleaner_id ) !== 'gb_cleaner' || get_post_status( $cleaner_id ) !== 'publish' ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    // Back to the reviews section, where the result banner sits above the
    // list and, for anything to put right, the form is already open.
    $back = function ( $code ) use ( $cleaner_id ) {
        wp_safe_redirect( add_query_arg( 'review', $code, get_permalink( $cleaner_id ) ) . '#gb-reviews' );
        exit;
    };

    // 1. Honeypot. People never see this field; form-filling bots fill it.
    if ( ! empty( $_POST['gb_review_website'] ) ) {
        $back( 'sent' );
    }

    // 2. Timing trap. Nobody reads a profile and writes a review in under
    //    four seconds. Lenient about a missing or old token, as on the
    //    contact form, so a cached page never swallows a real review.
    $timing = isset( $_POST['gb_review_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['gb_review_ts'] ) ) : '';
    if ( gb_form_timing_too_fast( $timing, GB_REVIEW_MIN_SECONDS ) ) {
        $back( 'sent' );
    }

    // 3. Nonce, tied to this cleaner.
    $nonce = isset( $_POST['gb_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['gb_review_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'gb_submit_review_' . $cleaner_id ) ) {
        $back( 'expired' );
    }

    // 4. Who is writing. A signed-in account always reviews as itself:
    //    whatever was posted in the name and email boxes is ignored.
    $user_id = get_current_user_id();
    if ( $user_id ) {
        $user  = wp_get_current_user();
        $name  = trim( (string) $user->display_name ) !== '' ? $user->display_name : $user->user_login;
        $email = $user->user_email;

        if ( function_exists( 'gb_get_cleaner_author_id' ) && gb_get_cleaner_author_id( $cleaner_id ) === $user_id ) {
            $back( 'own' );
        }
    } else {
        $name  = isset( $_POST['gb_review_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gb_review_name'] ) ) : '';
        $email = isset( $_POST['gb_review_email'] ) ? sanitize_email( wp_unslash( $_POST['gb_review_email'] ) ) : '';
    }
    $name  = mb_substr( trim( wp_strip_all_tags( $name ) ), 0, 80 );
    $email = strtolower( trim( (string) $email ) );

    $rating   = isset( $_POST['gb_rating'] ) ? (int) $_POST['gb_rating'] : 0;
    $raw_text = isset( $_POST['gb_review_text'] ) ? (string) wp_unslash( $_POST['gb_review_text'] ) : '';
    $text     = gb_clean_review_text( $raw_text );

    // 5. Things a real person can put right, so they are told.
    if ( $rating < 1 || $rating > 5 ) {
        $back( 'rating' );
    }
    if ( $name === '' ) {
        $back( 'name' );
    }
    if ( ! is_email( $email ) ) {
        $back( 'email' );
    }
    if ( mb_strlen( $text ) < GB_REVIEW_MIN_LENGTH ) {
        $back( 'short' );
    }
    if ( mb_strlen( $text ) > GB_REVIEW_MAX_LENGTH ) {
        $back( 'long' );
    }

    // 6. Rate limits: per email address (counted from stored reviews, so a
    //    new network does not reset it) and per connection.
    $ip_state = gb_rate_limit_check( 'review_submit_ip', GB_REVIEW_LIMIT_PER_IP, DAY_IN_SECONDS );
    if ( $ip_state['blocked'] || gb_count_recent_reviews_by_email( $email ) >= GB_REVIEW_LIMIT_PER_EMAIL ) {
        $back( 'sent' );
    }
    gb_record_rate_limit_hit( 'review_submit_ip', DAY_IN_SECONDS );

    // 7. One review per person per cleaner. An approved one stands (the
    //    team can unpublish it if it needs changing); a pending one is
    //    replaced by the newer version below.
    $existing = gb_find_existing_review( $cleaner_id, $email );
    if ( $existing && (string) $existing->comment_approved === '1' ) {
        $back( 'already' );
    }

    // 8. The same person sending the same words twice: keep the first.
    if ( gb_message_is_duplicate( $email, 'review-' . $cleaner_id, $text, WEEK_IN_SECONDS ) ) {
        $back( 'sent' );
    }

    // 9. Content scoring, on the raw text so the links still count against
    //    it. Spam is stored, not dropped, so a false positive can be put back
    //    from the Reviews tab, but it never reaches the approval queue.
    $spam_score = gb_message_spam_score( $name, $email, '', $raw_text );
    $is_spam    = gb_message_looks_like_spam( $name, $email, '', $raw_text ) || gb_review_text_seen_elsewhere( $email, $text );

    $verified = function_exists( 'gb_is_verified_client' ) && gb_is_verified_client( $email, $cleaner_id );

    if ( $existing && ! $is_spam ) {
        // Replace the pending review with the newer words and score.
        wp_update_comment( array(
            'comment_ID'       => (int) $existing->comment_ID,
            'comment_author'   => $name,
            'comment_content'  => $text,
            'comment_date'     => current_time( 'mysql' ),
            'comment_date_gmt' => current_time( 'mysql', true ),
            'comment_approved' => 0,
        ) );
        update_comment_meta( $existing->comment_ID, 'gb_rating', $rating );
        update_comment_meta( $existing->comment_ID, 'gb_verified_client', $verified ? '1' : '0' );
        update_comment_meta( $existing->comment_ID, 'gb_spam_score', $spam_score );
        gb_notify_team_of_review( (int) $existing->comment_ID, true );
        $back( 'sent' );
    }

    $comment_id = wp_insert_comment( array(
        'comment_post_ID'      => $cleaner_id,
        'comment_author'       => $name,
        'comment_author_email' => $email,
        'comment_author_url'   => '',
        'comment_author_IP'    => function_exists( 'gb_get_request_ip' ) ? gb_get_request_ip() : '',
        'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 254 ) : '',
        'comment_content'      => $text,
        'comment_type'         => 'comment',
        'comment_parent'       => 0,
        'user_id'              => $user_id,
        // Never 1. Approval is the manager's decision, always.
        'comment_approved'     => $is_spam ? 'spam' : 0,
        'comment_meta'         => array(
            'gb_rating'          => $rating,
            // Recorded at the time of writing: a booking made later should
            // not retro-fit a badge, and the team sees what was true then.
            'gb_verified_client' => $verified ? '1' : '0',
            'gb_spam_score'      => $spam_score,
            'gb_review_source'   => 'profile_form',
        ),
    ) );

    if ( $comment_id && ! $is_spam ) {
        gb_notify_team_of_review( (int) $comment_id );
    }

    $back( 'sent' );
}

/**
 * Tell the team a review is waiting, with everything needed to decide.
 */
function gb_notify_team_of_review( $comment_id, $updated = false ) {
    $comment = get_comment( $comment_id );
    if ( ! $comment ) {
        return;
    }

    $cleaner  = html_entity_decode( get_the_title( $comment->comment_post_ID ), ENT_QUOTES, 'UTF-8' );
    $stars    = (int) get_comment_meta( $comment_id, 'gb_rating', true );
    $verified = get_comment_meta( $comment_id, 'gb_verified_client', true ) === '1';
    $site     = get_bloginfo( 'name' );

    $subject = sprintf(
        '[%s] %s waiting for approval: %d %s for %s',
        $site,
        $updated ? 'Updated review' : 'New review',
        $stars,
        $stars === 1 ? 'star' : 'stars',
        $cleaner
    );

    $body = ( $updated
            ? "A reviewer has replaced their pending review on {$site}. It is still waiting for approval.\n\n"
            : "A new review is waiting for approval on {$site}. It is not on the profile until you approve it.\n\n" )
        . "Cleaner:         {$cleaner}\n"
        . "Rating:          {$stars} out of 5\n"
        . 'Reviewer:        ' . $comment->comment_author . ' <' . $comment->comment_author_email . ">\n"
        . 'Verified client: ' . ( $verified ? 'Yes, they have a booking with this cleaner' : 'No booking found under this email' ) . "\n\n"
        . "Review:\n" . $comment->comment_content . "\n\n"
        . "Approve or reject it in Manager OS:\n" . gb_get_reviews_tab_url() . "\n";

    wp_mail( gb_get_support_email(), $subject, $body );
}

/**
 * A short note to the cleaner when a review about them goes live. Sent once
 * per review, so unpublishing and re-approving does not repeat it.
 */
function gb_notify_cleaner_of_approved_review( $comment_id ) {
    $comment = get_comment( $comment_id );
    if ( ! $comment || get_comment_meta( $comment_id, 'gb_cleaner_notified', true ) ) {
        return;
    }

    $author_id = function_exists( 'gb_get_cleaner_author_id' ) ? gb_get_cleaner_author_id( $comment->comment_post_ID ) : 0;
    $user      = $author_id ? get_userdata( $author_id ) : false;
    if ( ! $user || ! is_email( $user->user_email ) ) {
        return;
    }

    $stars   = (int) get_comment_meta( $comment_id, 'gb_rating', true );
    $first   = trim( (string) $user->first_name ) !== '' ? $user->first_name : $user->display_name;
    $subject = 'You have a new review on The Green Broom';
    $body    = "Hi {$first},\n\n"
        . 'A new ' . $stars . '-star review from ' . $comment->comment_author . " is now live on your profile:\n\n"
        . $comment->comment_content . "\n\n"
        . "See it here:\n" . get_permalink( $comment->comment_post_ID ) . "#gb-reviews\n\n"
        . "The Green Broom team\n";

    if ( wp_mail( $user->user_email, $subject, $body ) ) {
        update_comment_meta( $comment_id, 'gb_cleaner_notified', current_time( 'mysql' ) );
    }
}

/*
 * wp_set_comment_status( 'approve' ) also queues WordPress's own "New comment
 * on your post" email to the post author, which for a cleaner profile is the
 * cleaner, and is full of wp-admin links they cannot use. Our own note above
 * replaces it.
 */
add_filter( 'notify_post_author', function ( $notify, $comment_id ) {
    $comment = get_comment( $comment_id );
    if ( $comment && get_post_type( $comment->comment_post_ID ) === 'gb_cleaner' ) {
        return false;
    }

    return $notify;
}, 10, 2 );

/* --------------------------------------------------------------------------
   MANAGER OS: APPROVE, REJECT, SPAM, UNPUBLISH
   --------------------------------------------------------------------------
   Every status change goes through wp_set_comment_status(), which fires the
   hook in cleaner-profiles.php that recalculates the cleaner's average. Each
   button carries its own nonce for that action on that review.
   -------------------------------------------------------------------------- */
function gb_review_moderation_actions() {
    return array(
        'approve'   => 'approve',
        'reject'    => 'trash',
        'spam'      => 'spam',
        'unpublish' => 'hold',
        'restore'   => 'hold',
    );
}

add_action( 'init', 'gb_handle_review_moderation' );
function gb_handle_review_moderation() {
    if ( ! isset( $_POST['gb_review_moderate'] ) ) {
        return;
    }

    $do         = sanitize_key( wp_unslash( $_POST['gb_review_moderate'] ) );
    $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
    $actions    = gb_review_moderation_actions();
    $bounce     = function ( $result ) {
        wp_safe_redirect( gb_get_reviews_tab_url( array( 'rv' => $result ) ) );
        exit;
    };

    if ( ! gb_user_can_moderate_reviews() ) {
        wp_safe_redirect( home_url( '/login' ) );
        exit;
    }

    $nonce = isset( $_POST['gb_review_mod_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['gb_review_mod_nonce'] ) ) : '';
    if ( ! isset( $actions[ $do ] ) || ! $comment_id || ! wp_verify_nonce( $nonce, 'gb_review_' . $do . '_' . $comment_id ) ) {
        $bounce( 'expired' );
    }

    $comment = get_comment( $comment_id );
    if ( ! $comment || get_post_type( $comment->comment_post_ID ) !== 'gb_cleaner' ) {
        $bounce( 'missing' );
    }

    $current = (string) $comment->comment_approved;
    $target  = $actions[ $do ];
    $already = ( $target === 'approve' && $current === '1' ) || ( $target === 'hold' && $current === '0' ) || $target === $current;

    /*
     * A second press of the same button (a double click, or two managers at
     * once) finds the review already there. That is a success, not a
     * failure, so it is not sent back to the database to be refused.
     *
     * Reject and spam go through wp_trash_comment() / wp_spam_comment(),
     * which record the previous status (so wp-admin can restore it) and
     * then call wp_set_comment_status() themselves.
     */
    if ( ! $already ) {
        if ( $target === 'trash' ) {
            $done = wp_trash_comment( $comment_id );
        } elseif ( $target === 'spam' ) {
            $done = wp_spam_comment( $comment_id );
        } else {
            $done = wp_set_comment_status( $comment_id, $target );
        }
        if ( ! $done ) {
            $bounce( 'failed' );
        }
    }

    if ( $do === 'approve' ) {
        gb_notify_cleaner_of_approved_review( $comment_id );
    }

    $bounce( $do );
}

/* --------------------------------------------------------------------------
   PROFILE OUTPUT
   -------------------------------------------------------------------------- */

/**
 * The result banner after a submission, from the ?review= flag.
 */
function gb_render_review_notice() {
    if ( empty( $_GET['review'] ) ) {
        return '';
    }

    $code     = sanitize_key( wp_unslash( $_GET['review'] ) );
    $messages = gb_review_notice_messages();
    if ( ! isset( $messages[ $code ] ) ) {
        return '';
    }

    $text = $code === 'already' ? sprintf( $messages[ $code ], gb_get_support_email() ) : $messages[ $code ];
    $ok   = $code === 'sent';

    // Drop the flag from the address bar once shown, so a later refresh or a
    // shared link does not repeat the message.
    $tidy = '<script>try{var u=new URL(window.location.href);u.searchParams.delete("review");history.replaceState(null,"",u.toString());}catch(e){}</script>';

    return '<div class="gb-review-notice ' . ( $ok ? 'is-ok' : 'is-error' ) . '" role="' . ( $ok ? 'status' : 'alert' ) . '">'
        . esc_html( $text ) . '</div>' . $tidy;
}

/**
 * The "Write a review" form. Kept here rather than in the template so the
 * field names and the handler cannot drift apart.
 */
function gb_render_review_form( $cleaner_id ) {
    $cleaner_id = (int) $cleaner_id;
    $code       = isset( $_GET['review'] ) ? sanitize_key( wp_unslash( $_GET['review'] ) ) : '';
    // Open straight away when coming back with something to fix.
    $open       = $code !== '' && $code !== 'sent';
    $user       = wp_get_current_user();
    $signed_in  = $user && $user->exists();
    $uid        = 'gb-review-' . $cleaner_id;
    $field_css  = 'width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:8px; font-family:inherit; font-size:16px; box-sizing:border-box;';
    $label_css  = 'display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:#334155;';
    ?>
    <button type="button" id="<?php echo esc_attr( $uid ); ?>-open" class="gb-btn-msg" style="width:auto; display:<?php echo $open ? 'none' : 'inline-block'; ?>; font-size:13px; padding:10px 20px;"
        onclick="document.getElementById('gb-review-form-wrap').style.display='block'; this.style.display='none';">+ Write a Review</button>

    <div id="gb-review-form-wrap" style="display:<?php echo $open ? 'block' : 'none'; ?>; margin-top:18px; background:#f7f9fb; padding:22px; border-radius:13px; border:1px solid #e8ecf1;">
        <p style="margin:0 0 16px; font-size:13.5px; color:#64748b; line-height:1.55;">Every review is checked by our team before it goes live. We never show your email address.</p>

        <form method="post" action="<?php echo esc_url( get_permalink( $cleaner_id ) ); ?>" class="gb-review-form">
            <input type="hidden" name="gb_review_action" value="submit_review">
            <input type="hidden" name="gb_review_cleaner" value="<?php echo (int) $cleaner_id; ?>">
            <input type="hidden" name="gb_review_ts" value="<?php echo esc_attr( gb_form_timing_token() ); ?>">
            <?php wp_nonce_field( 'gb_submit_review_' . $cleaner_id, 'gb_review_nonce', false ); ?>

            <?php // Honeypot: hidden from people and screen readers, filled by bots. ?>
            <div style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                <label for="<?php echo esc_attr( $uid ); ?>-website">Leave this empty</label>
                <input type="text" id="<?php echo esc_attr( $uid ); ?>-website" name="gb_review_website" value="" autocomplete="off" tabindex="-1">
            </div>

            <fieldset class="comment-form-rating" style="margin:0 0 16px; border:0; padding:0;">
                <legend style="<?php echo esc_attr( $label_css ); ?> padding:0;">Your Rating</legend>
                <span class="gb-star-pick">
                    <?php // Reversed order so the CSS sibling selector can fill stars to the left. ?>
                    <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                        <input type="radio" id="<?php echo esc_attr( $uid . '-star-' . $i ); ?>" name="gb_rating" value="<?php echo (int) $i; ?>"<?php checked( $i, 5 ); ?>>
                        <label for="<?php echo esc_attr( $uid . '-star-' . $i ); ?>" title="<?php echo esc_attr( $i . ' star' . ( $i === 1 ? '' : 's' ) ); ?>">&#9733;</label>
                    <?php endfor; ?>
                </span>
            </fieldset>

            <?php if ( $signed_in ) : ?>
                <p style="margin:0 0 14px; font-size:13.5px; color:#334155;">
                    Reviewing as <strong><?php echo esc_html( $user->display_name ); ?></strong>
                    (<?php echo esc_html( $user->user_email ); ?>)
                </p>
            <?php else : ?>
                <div class="gb-review-who" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:14px;">
                    <p style="margin:0;">
                        <label for="<?php echo esc_attr( $uid ); ?>-name" style="<?php echo esc_attr( $label_css ); ?>">Your Name</label>
                        <input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="gb_review_name" required maxlength="80" autocomplete="name" style="<?php echo esc_attr( $field_css ); ?>">
                    </p>
                    <p style="margin:0;">
                        <label for="<?php echo esc_attr( $uid ); ?>-email" style="<?php echo esc_attr( $label_css ); ?>">Your Email</label>
                        <input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="gb_review_email" required maxlength="190" autocomplete="email" style="<?php echo esc_attr( $field_css ); ?>">
                    </p>
                </div>
            <?php endif; ?>

            <p style="margin:0 0 14px;">
                <label for="<?php echo esc_attr( $uid ); ?>-text" style="<?php echo esc_attr( $label_css ); ?>">Your Review</label>
                <textarea id="<?php echo esc_attr( $uid ); ?>-text" name="gb_review_text" rows="5" required
                    minlength="<?php echo (int) GB_REVIEW_MIN_LENGTH; ?>" maxlength="<?php echo (int) GB_REVIEW_MAX_LENGTH; ?>"
                    placeholder="How did the clean go? What stood out?"
                    style="<?php echo esc_attr( $field_css ); ?> resize:vertical;"></textarea>
                <span style="display:block; margin-top:5px; font-size:12px; color:#64748b;">Between <?php echo (int) GB_REVIEW_MIN_LENGTH; ?> and <?php echo (int) GB_REVIEW_MAX_LENGTH; ?> characters. Links are removed.</span>
            </p>

            <button type="submit" class="gb-btn-book" style="margin:0;">Send Review for Approval</button>
        </form>
    </div>
    <?php
}
