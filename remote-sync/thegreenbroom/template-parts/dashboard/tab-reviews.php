<?php
/**
 * Tab Content: Reviews
 *
 * Every review written on a cleaner profile lands here as pending, and is
 * only shown on the profile once a manager approves it. The buttons post to
 * gb_handle_review_moderation() in inc/reviews.php, which checks the role and
 * a per-review, per-action nonce, then changes the status with WordPress's
 * own functions so the cleaner's average rating is recalculated each time.
 *
 * Laid out as cards rather than a table: a review is a paragraph of text, and
 * a table wide enough to hold one had to scroll sideways on a phone.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'gb_user_can_moderate_reviews' ) || ! gb_user_can_moderate_reviews() ) {
    echo '<div class="gb-card">You do not have permission to manage reviews.</div>';
    return;
}

$gb_rv_base = array(
    'post_type' => 'gb_cleaner',
    'type'      => 'comment',
);

// Oldest first, so nothing waits at the bottom of the pile.
$gb_rv_pending  = get_comments( array_merge( $gb_rv_base, array( 'status' => 'hold', 'orderby' => 'comment_date_gmt', 'order' => 'ASC', 'number' => 100 ) ) );
$gb_rv_approved = get_comments( array_merge( $gb_rv_base, array( 'status' => 'approve', 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'number' => 20 ) ) );
$gb_rv_spam     = get_comments( array_merge( $gb_rv_base, array( 'status' => 'spam', 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'number' => 20 ) ) );

$gb_rv_pending_total  = (int) get_comments( array_merge( $gb_rv_base, array( 'status' => 'hold', 'count' => true ) ) );
$gb_rv_approved_total = (int) get_comments( array_merge( $gb_rv_base, array( 'status' => 'approve', 'count' => true ) ) );
$gb_rv_spam_total     = (int) get_comments( array_merge( $gb_rv_base, array( 'status' => 'spam', 'count' => true ) ) );

// Result of the last button press.
$gb_rv_result  = isset( $_GET['rv'] ) ? sanitize_key( wp_unslash( $_GET['rv'] ) ) : '';
$gb_rv_notices = array(
    'approve'   => array( 'ok', 'Review approved. It is now live on the cleaner\'s profile and their average has been updated.' ),
    'reject'    => array( 'warn', 'Review rejected and moved to the bin. It will not appear on the site.' ),
    'spam'      => array( 'warn', 'Review marked as spam.' ),
    'unpublish' => array( 'warn', 'Review taken off the profile. It is back in the pending list below.' ),
    'restore'   => array( 'ok', 'Review moved out of spam and into the pending list.' ),
    'expired'   => array( 'error', 'That page had been open too long to act safely, so nothing was changed. Please press the button again.' ),
    'missing'   => array( 'error', 'That review could not be found. It may already have been dealt with.' ),
    'failed'    => array( 'error', 'That change could not be saved. Please try again.' ),
);

/**
 * One button, as its own small form with its own nonce. Inputs are printed
 * by hand rather than with wp_nonce_field(), which would repeat the same id
 * on every card.
 */
$gb_rv_button = function ( $comment_id, $do, $label, $style = '', $confirm = '' ) {
    ?>
    <form method="post" class="gb-rv-act" <?php echo $confirm !== '' ? 'onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : ''; ?>>
        <input type="hidden" name="gb_review_moderate" value="<?php echo esc_attr( $do ); ?>">
        <input type="hidden" name="comment_id" value="<?php echo (int) $comment_id; ?>">
        <input type="hidden" name="gb_review_mod_nonce" value="<?php echo esc_attr( wp_create_nonce( 'gb_review_' . $do . '_' . (int) $comment_id ) ); ?>">
        <button type="submit" class="<?php echo $do === 'approve' ? 'gb-btn-primary-os' : 'gb-btn-small-os'; ?>" style="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( $label ); ?></button>
    </form>
    <?php
};

/**
 * One review card.
 */
$gb_rv_card = function ( $comment, $context ) use ( $gb_rv_button ) {
    $cid       = (int) $comment->comment_ID;
    $cleaner   = (int) $comment->comment_post_ID;
    $stars     = (int) get_comment_meta( $cid, 'gb_rating', true );
    $flag      = get_comment_meta( $cid, 'gb_verified_client', true );
    // Reviews from before this form have no stored flag; check live instead.
    $verified  = $flag !== '' ? $flag === '1' : ( function_exists( 'gb_is_verified_client' ) && gb_is_verified_client( $comment->comment_author_email, $cleaner ) );
    $score     = get_comment_meta( $cid, 'gb_spam_score', true );
    ?>
    <article class="gb-os-card gb-rv-card">
        <div class="gb-rv-top">
            <div class="gb-rv-who">
                <strong><?php echo esc_html( $comment->comment_author !== '' ? $comment->comment_author : 'No name given' ); ?></strong>
                <?php if ( $verified ) : ?>
                    <span class="gb-rv-badge is-verified" title="Had a booking with this cleaner when they wrote the review">&#10003; Verified client</span>
                <?php else : ?>
                    <span class="gb-rv-badge">No booking found</span>
                <?php endif; ?>
                <a class="gb-rv-email" href="mailto:<?php echo esc_attr( $comment->comment_author_email ); ?>"><?php echo esc_html( $comment->comment_author_email ); ?></a>
            </div>
            <div class="gb-rv-stars" aria-label="<?php echo esc_attr( $stars . ' out of 5 stars' ); ?>">
                <?php echo $stars > 0 && function_exists( 'gb_render_stars' ) ? gb_render_stars( $stars, 16 ) : '<span class="gb-rv-muted">No rating</span>'; ?>
            </div>
        </div>

        <div class="gb-rv-meta">
            For <a href="<?php echo esc_url( get_permalink( $cleaner ) ); ?>#gb-reviews" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $cleaner ) ); ?></a>
            <span aria-hidden="true">&middot;</span>
            <?php echo esc_html( get_comment_date( 'j M Y, H:i', $comment ) ); ?>
            <?php if ( $context === 'spam' && $score !== '' ) : ?>
                <span aria-hidden="true">&middot;</span> Spam score <?php echo (int) $score; ?>
            <?php endif; ?>
        </div>

        <div class="gb-rv-text"><?php echo wpautop( esc_html( $comment->comment_content ) ); ?></div>

        <div class="gb-rv-actions">
            <?php
            if ( $context === 'pending' ) {
                $gb_rv_button( $cid, 'approve', 'Approve' );
                $gb_rv_button( $cid, 'reject', 'Reject', 'color:#991b1b !important; border-color:#fecaca !important;', 'Reject this review? It will be moved to the bin and never shown.' );
                $gb_rv_button( $cid, 'spam', 'Mark as spam' );
            } elseif ( $context === 'approved' ) {
                $gb_rv_button( $cid, 'unpublish', 'Unpublish', '', 'Take this review off the profile? It will go back to the pending list.' );
            } else {
                $gb_rv_button( $cid, 'restore', 'Not spam, move to pending' );
                $gb_rv_button( $cid, 'reject', 'Delete', 'color:#991b1b !important; border-color:#fecaca !important;', 'Move this review to the bin?' );
            }
            ?>
        </div>
    </article>
    <?php
};
?>

<style>
    .gb-rv-summary { display: flex; flex-wrap: wrap; gap: 8px; }
    .gb-rv-pill { display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #e2e8f0; border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; }
    .gb-rv-pill strong { color: #0f172a; }
    .gb-rv-pill.is-waiting { background: #fffbeb; border-color: #fde68a; color: #92400e; }

    .gb-rv-notice { padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; font-size: 14px; border: 1px solid; }
    .gb-rv-notice.is-ok { background: #f0fdf4; border-color: #dcfce7; color: #166534; }
    .gb-rv-notice.is-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .gb-rv-notice.is-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

    .gb-rv-section { margin: 0 0 32px; }
    .gb-rv-section h2 { font-size: 13px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .6px; margin: 0 0 12px; }
    .gb-rv-list { display: flex; flex-direction: column; gap: 12px; }
    .gb-rv-empty { padding: 20px !important; color: #64748b; font-size: 14px; }

    .gb-rv-card { padding: 18px 20px !important; min-width: 0; }
    .gb-rv-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px 16px; flex-wrap: wrap; }
    .gb-rv-who { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 10px; min-width: 0; font-size: 14px; color: #0f172a; }
    .gb-rv-email { color: #64748b; font-size: 12.5px; text-decoration: none; overflow-wrap: anywhere; }
    .gb-rv-email:hover { text-decoration: underline; }
    .gb-rv-badge { background: #f1f5f9; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; padding: 3px 8px; border-radius: 999px; white-space: nowrap; }
    .gb-rv-badge.is-verified { background: #dcfce7; color: #166534; }
    .gb-rv-meta { margin-top: 6px; font-size: 12.5px; color: #64748b; overflow-wrap: anywhere; }
    .gb-rv-meta a { color: #2e7d32; font-weight: 700; text-decoration: none; }
    .gb-rv-text { margin-top: 12px; font-size: 14px; line-height: 1.65; color: #334155; overflow-wrap: anywhere; }
    .gb-rv-text p { margin: 0 0 8px; }
    .gb-rv-text p:last-child { margin-bottom: 0; }
    .gb-rv-muted { color: #94a3b8; font-size: 12px; }
    .gb-rv-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
    .gb-rv-act { margin: 0; }
    .gb-rv-act button { cursor: pointer; }
    /* Same height as the buttons beside it. The dashboard's unified rule makes
       primary buttons 44px tall, so this needs the same :not() to outrank it. */
    .gb-main:not(.gb-tab-overview) .gb-rv-actions .gb-btn-primary-os { height: auto !important; padding: 9px 18px !important; font-size: 12px !important; line-height: normal; }

    .gb-rv-spam > summary { cursor: pointer; font-size: 13px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 12px; }
    .gb-rv-spam > p { color: #64748b; font-size: 13px; margin: 0 0 12px; }

    @media (max-width: 760px) {
        .gb-rv-actions .gb-rv-act { flex: 1 1 auto; }
        .gb-rv-actions button { width: 100%; justify-content: center; }
    }
</style>

<div class="booking-controls" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; gap:16px; flex-wrap:wrap;">
    <div>
        <h1 class="gb-title">Reviews</h1>
        <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Every review waits here until you approve it. Nothing appears on a cleaner's profile without your say-so.</p>
    </div>
    <div class="gb-rv-summary">
        <span class="gb-rv-pill <?php echo $gb_rv_pending_total ? 'is-waiting' : ''; ?>"><strong><?php echo (int) $gb_rv_pending_total; ?></strong> waiting</span>
        <span class="gb-rv-pill"><strong><?php echo (int) $gb_rv_approved_total; ?></strong> live</span>
        <span class="gb-rv-pill"><strong><?php echo (int) $gb_rv_spam_total; ?></strong> spam</span>
    </div>
</div>

<?php if ( isset( $gb_rv_notices[ $gb_rv_result ] ) ) : ?>
    <div class="gb-rv-notice is-<?php echo esc_attr( $gb_rv_notices[ $gb_rv_result ][0] ); ?>" role="status"><?php echo esc_html( $gb_rv_notices[ $gb_rv_result ][1] ); ?></div>
<?php endif; ?>

<section class="gb-rv-section">
    <h2>Waiting for approval (<?php echo (int) $gb_rv_pending_total; ?>)</h2>
    <div class="gb-rv-list">
        <?php if ( $gb_rv_pending ) : ?>
            <?php foreach ( $gb_rv_pending as $gb_rv_comment ) { $gb_rv_card( $gb_rv_comment, 'pending' ); } ?>
        <?php else : ?>
            <div class="gb-os-card gb-rv-empty">Nothing waiting. New reviews will appear here, and the team inbox gets an email for each one.</div>
        <?php endif; ?>
    </div>
</section>

<section class="gb-rv-section">
    <h2>Recently approved</h2>
    <div class="gb-rv-list">
        <?php if ( $gb_rv_approved ) : ?>
            <?php foreach ( $gb_rv_approved as $gb_rv_comment ) { $gb_rv_card( $gb_rv_comment, 'approved' ); } ?>
        <?php else : ?>
            <div class="gb-os-card gb-rv-empty">No approved reviews yet.</div>
        <?php endif; ?>
    </div>
</section>

<?php if ( $gb_rv_spam ) : ?>
<section class="gb-rv-section">
    <details class="gb-rv-spam">
        <summary>Caught as spam (<?php echo (int) $gb_rv_spam_total; ?>)</summary>
        <p>These were flagged automatically (links, promotional wording or the same text under several names) and never reached the list above. If one is genuine, move it back to pending.</p>
        <div class="gb-rv-list">
            <?php foreach ( $gb_rv_spam as $gb_rv_comment ) { $gb_rv_card( $gb_rv_comment, 'spam' ); } ?>
        </div>
    </details>
</section>
<?php endif; ?>
