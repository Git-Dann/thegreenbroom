<?php
/* Template Name: Cleaner Profile (Single) */
get_header();

$cleaner_id   = get_the_ID();
$rate         = get_post_meta($cleaner_id, 'hourly_rate', true) ?: '15';
// The same test as the sidebar and the listing card: every check on file, not
// just "published". The header badge used to say Verified for a cleaner the
// manager list showed as "Live - checks due".
$verified     = function_exists('gb_cleaner_is_fully_verified') ? gb_cleaner_is_fully_verified($cleaner_id) : get_post_meta($cleaner_id, 'is_verified', true) === '1';
$location     = function_exists('gb_get_cleaner_location') ? gb_get_cleaner_location($cleaner_id) : get_post_meta($cleaner_id, 'location', true);
$member_since = get_the_date('Y', $cleaner_id);

$rating = function_exists('gb_get_cleaner_rating') ? gb_get_cleaner_rating($cleaner_id) : ['average' => 0, 'rated' => 0, 'total' => 0];

// Only completed, customer-relevant checks reach the public profile.
$public_badges = function_exists('gb_get_public_cleaner_badges') ? gb_get_public_cleaner_badges($cleaner_id) : [];

// Bio: capture once so the fallback can't print after the real content.
/*
 * Read the bio off the post object, not get_the_content(). This template has
 * no have_posts()/the_post() loop, so the $page/$pages globals that
 * get_the_content() relies on are never set and it returns an empty string —
 * which meant every manager-written bio was discarded at render and every
 * profile fell back to the generic sentence below.
 */
$bio_post = get_post(get_the_ID());
$bio_raw  = $bio_post ? trim((string) $bio_post->post_content) : '';

// Real service catalogue rather than a hard-coded list that is the same for
// every cleaner.
/*
 * The services THIS cleaner offers, from their own gb_services meta.
 *
 * This previously queried every published gb_service, so every profile
 * claimed the cleaner offered the full catalogue regardless of what they had
 * actually selected - a misrepresentation to customers, and it disagreed with
 * the service filter on the listings page, which does read the meta.
 *
 * Shown in catalogue order, and only for services still published, so a
 * retired or renamed service does not linger on a profile.
 */
$gb_selected_services = get_post_meta( $cleaner_id, 'gb_services', true );
$gb_selected_services = is_array( $gb_selected_services ) ? array_filter( array_map( 'trim', $gb_selected_services ) ) : array();

$gb_services = array();
if ( ! empty( $gb_selected_services ) ) {
    $gb_services = get_posts([
        'post_type'      => 'gb_service',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ]);
    $gb_services = array_values( array_filter( $gb_services, function ( $service ) use ( $gb_selected_services ) {
        return in_array( $service->post_title, $gb_selected_services, true );
    } ) );
}
?>

<style>
    /* ---------- HERO ---------- */
    /* Centring is forced explicitly here: the avatar and badge were rendering
       hard-left because text-align alone doesn't centre a block-level image,
       and a flex ancestor would ignore it too. display:block + margin auto
       centres in normal flow, align-self covers the flex case, and float:none
       guards against any inherited float. */
    .gb-profile-header {
        background: linear-gradient(160deg, #2e7d32 0%, #1b5e20 100%);
        color: #fff; padding: 64px 20px 110px; text-align: center; position: relative;
        display: block;
    }
    .gb-profile-avatar {
        width: 132px; height: 132px; border-radius: 50%; border: 5px solid rgba(255,255,255,0.9);
        object-fit: cover; box-shadow: 0 14px 34px rgba(0,0,0,0.22); background: #fff;
        display: block; margin-left: auto; margin-right: auto; margin-bottom: 18px;
        float: none; align-self: center;
    }
    .gb-profile-header > * { margin-left: auto; margin-right: auto; }
    .gb-profile-name { font-size: clamp(26px, 4vw, 36px); font-weight: 800; margin: 0; color: #fff !important; letter-spacing: -0.5px; }
    .gb-verified-badge {
        background: #fff; color: #1b5e20; padding: 7px 15px; border-radius: 999px; font-size: 12px;
        font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 7px;
        margin-top: 14px; letter-spacing: 0.6px; box-shadow: 0 6px 16px rgba(0,0,0,0.14);
    }
    .gb-verified-badge img { height: 14px; width: auto; }

    /* Meta strip under the name */
    .gb-hero-meta {
        margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px 26px;
        justify-content: center; align-items: center; color: rgba(255,255,255,0.92); font-size: 14px; font-weight: 600;
    }
    .gb-hero-meta span.gb-hero-item { display: inline-flex; align-items: center; gap: 7px; }

    /* ---------- LAYOUT ---------- */
    .gb-profile-container { max-width: 1060px; margin: -70px auto 70px; padding: 0 20px; position: relative; z-index: 10; }
    .gb-profile-card {
        background: #fff; border-radius: 18px; box-shadow: 0 18px 44px rgba(15,23,42,0.08);
        border: 1px solid #e8ecf1; display: grid; grid-template-columns: minmax(0,2fr) minmax(0,1fr); overflow: hidden;
    }
    .gb-profile-content { padding: 44px; min-width: 0; }
    .gb-profile-sidebar { background: #f7f9fb; padding: 36px 28px; border-left: 1px solid #e8ecf1; }
    .gb-section-title {
        font-size: 12px; font-weight: 800; color: #64748b; margin: 0 0 16px;
        text-transform: uppercase; letter-spacing: 1px;
    }
    .gb-block { margin-bottom: 40px; }
    .gb-bio-text { line-height: 1.75; color: #334155; font-size: 15.5px; }
    .gb-bio-text p { margin: 0 0 14px; }

    /* ---------- VERIFIED CHECKS (sidebar icons + tooltip) ---------- */
    .gb-verify { margin-top: 22px; padding-top: 20px; border-top: 1px solid #e8ecf1; }
    .gb-verify-head { font-size: 12px; font-weight: 800; color: #1b5e20; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 14px; text-align: center; }
    /* The client's spec shows these checks as readable ticked lines rather
       than icon-only chips, so the label sits beside the icon. */
    .gb-verify-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .gb-verify-item { display: flex; align-items: center; gap: 10px; }
    .gb-verify-ico { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 28px; width: 28px; height: 28px; border-radius: 8px; background: #f0fdf4; color: #2e7d32; }
    .gb-verify-ico svg { width: 16px; height: 16px; display: block; }
    .gb-verify-label { font-size: 13px; font-weight: 600; color: #334155; line-height: 1.35; }
    .gb-verify-date { margin: 14px 0 0; text-align: center; font-size: 11.5px; color: #64748b; }
    .gb-verify-row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .gb-verify-chip {
        width: 44px; height: 44px; border-radius: 12px; background: #f2fdf5; border: 1px solid #dcfce7;
        color: #1b7a33; display: inline-flex; align-items: center; justify-content: center;
        position: relative; cursor: help; outline: none; transition: background .15s, border-color .15s;
    }
    .gb-verify-chip:hover, .gb-verify-chip:focus-visible { background: #e4f8ea; border-color: #bbe9c6; }
    /* Tooltip */
    .gb-verify-chip::after {
        content: attr(data-tip); position: absolute; bottom: calc(100% + 9px); left: 50%; transform: translateX(-50%);
        background: #0f172a; color: #fff; font-size: 11.5px; font-weight: 700; letter-spacing: .2px;
        padding: 6px 10px; border-radius: 7px; white-space: nowrap; opacity: 0; pointer-events: none;
        transition: opacity .15s; z-index: 5;
    }
    .gb-verify-chip::before {
        content: ""; position: absolute; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%);
        border: 5px solid transparent; border-top-color: #0f172a; opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 5;
    }
    .gb-verify-chip:hover::after, .gb-verify-chip:focus-visible::after,
    .gb-verify-chip:hover::before, .gb-verify-chip:focus-visible::before { opacity: 1; }

    /* ---------- SERVICES ---------- */
    .gb-services-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 11px; }
    .gb-services-list li { display: flex; align-items: center; gap: 9px; font-size: 14.5px; color: #475569; font-weight: 500; }
    .gb-check-icon { color: #2e7d32; font-weight: 900; }

    /* ---------- REVIEWS ---------- */
    .gb-rating-summary {
        display: flex; align-items: center; gap: 22px; padding: 20px 22px; border: 1px solid #e8ecf1;
        border-radius: 14px; background: #fbfcfd; margin-bottom: 22px; flex-wrap: wrap;
    }
    .gb-rating-score { font-size: 42px; font-weight: 800; color: #0f172a; line-height: 1; }
    .gb-rating-meta { color: #64748b; font-size: 13.5px; margin-top: 5px; }
    .gb-review-card { background: #fff; border-radius: 13px; padding: 20px; margin-bottom: 14px; border: 1px solid #e8ecf1; text-align: left; }
    .gb-rev-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 9px; flex-wrap: wrap; }
    .gb-rev-author { font-weight: 800; color: #0f172a; font-size: 14.5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .gb-rev-verified { background: #dcfce7; color: #166534; font-size: 10px; padding: 3px 8px; border-radius: 999px; text-transform: uppercase; font-weight: 800; letter-spacing: 0.4px; }
    .gb-rev-date { font-size: 12px; color: #64748b; white-space: nowrap; }
    .gb-rev-body { font-size: 14.5px; color: #475569; line-height: 1.7; margin-top: 8px; }
    .gb-rev-body p { margin: 0; }

    /* Star picker on the review form */
    .gb-star-pick { display: inline-flex; flex-direction: row-reverse; gap: 4px; }
    .gb-star-pick input { position: absolute; opacity: 0; width: 0; height: 0; }
    .gb-star-pick label { font-size: 26px; color: #d7dbe0; cursor: pointer; line-height: 1; transition: color .12s; }
    .gb-star-pick input:checked ~ label,
    .gb-star-pick label:hover, .gb-star-pick label:hover ~ label { color: #f59e0b; }
    .gb-star-pick input:focus-visible + label { outline: 2px solid #2e7d32; outline-offset: 2px; border-radius: 4px; }

    /* Result of sending a review (?review= flag from inc/reviews.php) */
    .gb-review-notice { padding: 14px 16px; border-radius: 12px; margin-bottom: 18px; font-size: 14px; font-weight: 600; line-height: 1.5; border: 1px solid; }
    .gb-review-notice.is-ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .gb-review-notice.is-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

    /* ---------- SIDEBAR ---------- */
    .gb-price-box { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #e8ecf1; margin-bottom: 20px; }
    .gb-price-tag { font-size: 40px; font-weight: 800; color: #0f172a; display: block; line-height: 1; }
    .gb-price-sub { font-size: 13.5px; color: #64748b; display: block; font-weight: 500; margin-top: 6px; }
    .gb-btn-book { display: block; width: 100%; background: #2e7d32; color: #fff; padding: 16px; border-radius: 10px; font-weight: 700; text-decoration: none; text-align: center; transition: .2s; box-shadow: 0 6px 16px rgba(46,125,50,.22); font-size: 15.5px; margin-bottom: 11px; border: none; cursor: pointer; box-sizing: border-box; }
    .gb-btn-book:hover { background: #1b5e20; }
    .gb-btn-msg { display: block; width: 100%; background: #fff; color: #0f172a; border: 1.5px solid #dfe5ec; padding: 14px; border-radius: 10px; font-weight: 700; text-decoration: none; text-align: center; transition: .2s; font-size: 14.5px; box-sizing: border-box; cursor: pointer; }
    .gb-btn-msg:hover { border-color: #cbd5e1; background: #f1f5f9; }
    .gb-members-contact { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:18px; margin-bottom:11px; text-align:center; }
    .gb-members-contact strong { display:block; color:#14532d; font-size:15px; margin-bottom:4px; }
    .gb-members-contact p { margin:0 0 14px; color:#166534; font-size:13.5px; line-height:1.5; }
    .gb-members-contact .gb-btn-book { margin-bottom:8px; }
    .gb-members-contact-login { display:inline-block; color:#166534; font-size:13px; font-weight:700; text-decoration:underline; }
    .gb-trust-list { list-style: none; padding: 0; margin: 22px 0 0; }
    .gb-trust-list li { display: flex; gap: 9px; align-items: flex-start; font-size: 13px; color: #64748b; margin-bottom: 10px; line-height: 1.5; }
    .gb-trust-list li span { color: #2e7d32; font-weight: 900; flex-shrink: 0; }

    /* ---------- MODAL ---------- */
    .gb-modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px; }
    .gb-modal-content { background: #fff; padding: 36px; border-radius: 16px; width: 100%; max-width: 450px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25); text-align: left; max-height: 90vh; overflow-y: auto; }
    .gb-close-modal { position: absolute; top: 14px; right: 18px; font-size: 24px; cursor: pointer; color: #64748b; }
    .gb-modal input, .gb-modal textarea { width: 100%; padding: 12px; margin-bottom: 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box; }
    .gb-modal textarea { height: 120px; resize: vertical; }
    .gb-modal label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #334155; }

    @media (max-width: 860px) {
        .gb-profile-card { grid-template-columns: 1fr; }
        .gb-profile-sidebar { border-left: none; border-top: 1px solid #e8ecf1; }
        .gb-profile-content { padding: 30px 22px; }
        .gb-profile-header { padding: 48px 18px 96px; }
    }
</style>

<div class="gb-profile-header">
    <?php if (has_post_thumbnail()): ?>
        <?php the_post_thumbnail('medium', ['class' => 'gb-profile-avatar', 'alt' => esc_attr(get_the_title()) . ' profile photo']); ?>
    <?php else: ?>
        <img src="https://www.gravatar.com/avatar/?d=mp&s=150" class="gb-profile-avatar" alt="">
    <?php endif; ?>

    <h1 class="gb-profile-name"><?php the_title(); ?></h1>

    <?php if ($verified): ?>
        <div>
            <span class="gb-verified-badge">
                <img src="https://thegreenbroom.uk/wp-content/uploads/2025/12/tgb-icon-logo.png" alt="">
                Verified Professional
            </span>
        </div>
    <?php endif; ?>

    <div class="gb-hero-meta">
        <?php if ($rating['rated'] > 0): ?>
            <span class="gb-hero-item">
                <?php echo gb_render_stars($rating['average'], 15); ?>
                <strong><?php echo esc_html(number_format($rating['average'], 1)); ?></strong>
                (<?php echo (int) $rating['rated']; ?>)
            </span>
        <?php endif; ?>
        <span class="gb-hero-item">🗓️ Member since <?php echo esc_html($member_since); ?></span>
        <?php if (function_exists('gb_cleaner_is_fully_booked') && gb_cleaner_is_fully_booked($cleaner_id)): ?>
            <span class="gb-hero-item gb-hero-booked">Fully booked right now</span>
        <?php endif; ?>
        <?php if ($location): ?>
            <span class="gb-hero-item">📍 <?php echo esc_html($location); ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="gb-profile-container">
    <div class="gb-profile-card">

        <div class="gb-profile-content">

            <div class="gb-block">
                <h2 class="gb-section-title">About</h2>
                <div class="gb-bio-text">
                    <?php if ($bio_raw !== ''): ?>
                        <?php echo apply_filters('the_content', $bio_raw); ?>
                    <?php else: ?>
                        <p>A verified professional with The Green Broom, specialising in eco-friendly cleaning.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($gb_services)): ?>
            <div class="gb-block">
                <h2 class="gb-section-title">Services Available</h2>
                <ul class="gb-services-list">
                    <?php foreach ($gb_services as $service): ?>
                        <li><span class="gb-check-icon">✓</span> <?php echo esc_html(get_the_title($service)); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php
            /*
             * Availability, only when the cleaner has actually filled it in.
             * An empty grid would read as "never free", which is the opposite
             * of what a blank field means.
             */
            if ( function_exists( 'gb_get_availability_slots' ) && gb_get_availability_slots( $cleaner_id ) ) : ?>
            <div class="gb-block">
                <h2 class="gb-section-title">Usual Availability</h2>
                <?php gb_render_availability_summary( $cleaner_id ); ?>
            </div>
            <?php endif; ?>

            <div class="gb-block" id="gb-reviews" style="margin-bottom:0; scroll-margin-top:90px;">
                <?php echo function_exists( 'gb_render_review_notice' ) ? gb_render_review_notice() : ''; ?>
                <h2 class="gb-section-title">Client Reviews<?php echo $rating['total'] ? ' (' . (int) $rating['total'] . ')' : ''; ?></h2>

                <?php if ($rating['rated'] > 0): ?>
                    <div class="gb-rating-summary">
                        <div>
                            <div class="gb-rating-score"><?php echo esc_html(number_format($rating['average'], 1)); ?></div>
                        </div>
                        <div>
                            <?php echo gb_render_stars($rating['average'], 19); ?>
                            <div class="gb-rating-meta">
                                Based on <?php echo (int) $rating['rated']; ?> rated review<?php echo $rating['rated'] === 1 ? '' : 's'; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="gb-reviews-list">
                    <?php
                    $comments = get_comments(['post_id' => $cleaner_id, 'status' => 'approve', 'type' => 'comment']);
                    if ($comments) :
                        foreach ($comments as $comment) :
                            $c_email    = $comment->comment_author_email;
                            $is_client  = function_exists('gb_is_verified_client') ? gb_is_verified_client($c_email, $cleaner_id) : false;
                            $c_rating   = (int) get_comment_meta($comment->comment_ID, 'gb_rating', true);
                    ?>
                        <div class="gb-review-card">
                            <div class="gb-rev-header">
                                <div class="gb-rev-author">
                                    <?php echo esc_html(get_comment_author($comment)); ?>
                                    <?php if ($is_client): ?>
                                        <span class="gb-rev-verified" title="Booked with this cleaner">✓ Verified Client</span>
                                    <?php endif; ?>
                                </div>
                                <span class="gb-rev-date"><?php echo esc_html(get_comment_date('j M Y', $comment)); ?></span>
                            </div>
                            <?php if ($c_rating >= 1): ?>
                                <?php echo gb_render_stars($c_rating, 14); ?>
                            <?php endif; ?>
                            <div class="gb-rev-body"><?php echo wpautop(esc_html($comment->comment_content)); ?></div>
                        </div>
                    <?php endforeach;
                    else : ?>
                        <p style="color:#64748b;">No reviews yet. If you have booked with this cleaner, be the first to leave one.</p>
                    <?php endif; ?>
                </div>

                <?php
                /*
                 * Our own review form, not comment_form(). WordPress's comment
                 * form is closed on cleaner profiles on purpose (it was a spam
                 * target and published or held whatever it was sent), so
                 * comment_form() printed nothing and nobody could leave a
                 * review at all. The form and its handler live in
                 * inc/reviews.php: every review is held for a manager to
                 * approve in Manager OS, behind a honeypot, a timing check,
                 * rate limits and spam scoring.
                 */
                ?>
                <div style="margin-top:24px;">
                    <?php if ( function_exists( 'gb_render_review_form' ) ) { gb_render_review_form( $cleaner_id ); } ?>
                </div>
            </div>

        </div>

        <div class="gb-profile-sidebar">
            <div style="position: sticky; top: 24px;">
                <div class="gb-price-box">
                    <span class="gb-price-tag">£<?php echo esc_html($rate); ?></span>
                    <span class="gb-price-sub">per hour</span>
                </div>

                <?php $gb_can_contact = ! function_exists('gb_can_contact_cleaners') || gb_can_contact_cleaners(); ?>
                <?php if ( ! $gb_can_contact ) : ?>
                    <?php /* Browsing is open to everybody; booking and messaging are what membership buys. */ ?>
                    <?php gb_render_members_only_contact( get_permalink($cleaner_id) ); ?>
                <?php else : ?>
                    <?php if ( function_exists('gb_cleaner_can_take_bookings') && ! gb_cleaner_can_take_bookings($cleaner_id) ) : ?>
                        <?php /* No payout account means no booking, so do not offer one. Messaging still works. */ ?>
                        <span class="gb-btn-book gb-btn-book-off" aria-disabled="true"><?php echo function_exists('gb_bookings_paused') && gb_bookings_paused() ? 'Bookings paused for now' : 'Not taking bookings yet'; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url(home_url('/book-now/?cleaner=' . $cleaner_id)); ?>" class="gb-btn-book">Book Now</a>
                    <?php endif; ?>
                    <button onclick="openMsgModal()" class="gb-btn-msg">Message Professional</button>
                <?php endif; ?>

                <?php
                /*
                 * Only show the badge when every check behind it is complete.
                 * The pop-up tells customers the badge means ID, Basic DBS and
                 * two references have all been done, so showing it on a partial
                 * set would be a claim the platform has not actually met.
                 */
                $gb_fully_verified = function_exists('gb_cleaner_is_fully_verified') && gb_cleaner_is_fully_verified($cleaner_id);
                ?>
                <?php if ($gb_fully_verified && !empty($public_badges)): ?>
                <?php $gb_verified_on = function_exists('gb_get_cleaner_verified_date') ? gb_get_cleaner_verified_date($cleaner_id) : ''; ?>
                <div class="gb-verify">
                    <div class="gb-verify-head">&#127811; Green Broom Verified</div>
                    <ul class="gb-verify-list">
                        <?php foreach ($public_badges as $badge): ?>
                            <li class="gb-verify-item" title="<?php echo esc_attr($badge['note']); ?>">
                                <span class="gb-verify-ico" aria-hidden="true"><?php echo $badge['svg']; ?></span>
                                <span class="gb-verify-label"><?php echo esc_html($badge['label']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($gb_verified_on !== ''): ?>
                        <p class="gb-verify-date">Green Broom Verified &mdash; <?php echo esc_html($gb_verified_on); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php
                /*
                 * Track record, counted from confirmed bookings whose date has
                 * passed. Nobody types these in, so they cannot be inflated -
                 * and each one is left off entirely until it is above zero,
                 * rather than announcing that a new cleaner has done nothing.
                 */
                $gb_stats = function_exists( 'gb_get_cleaner_stats' ) ? gb_get_cleaner_stats( $cleaner_id ) : array( 'completed' => 0, 'repeat' => 0 );
                ?>
                <?php if ( $gb_stats['completed'] > 0 || $gb_stats['repeat'] > 0 ) : ?>
                <div class="gb-track-record">
                    <?php if ( $gb_stats['completed'] > 0 ) : ?>
                        <div class="gb-track-stat">
                            <span class="gb-track-stat-value"><?php echo (int) $gb_stats['completed']; ?></span>
                            <span class="gb-track-stat-label"><?php echo esc_html( _n( 'clean completed', 'cleans completed', $gb_stats['completed'], 'thegreenbroom' ) ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $gb_stats['repeat'] > 0 ) : ?>
                        <div class="gb-track-stat">
                            <span class="gb-track-stat-value"><?php echo (int) $gb_stats['repeat']; ?></span>
                            <span class="gb-track-stat-label"><?php echo esc_html( _n( 'repeat customer', 'repeat customers', $gb_stats['repeat'], 'thegreenbroom' ) ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <ul class="gb-trust-list">
                    <li><span>✓</span> Eco-friendly products</li>
                    <li><span>✓</span> Secure payment through the platform</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<div id="gb-msg-modal" class="gb-modal">
    <div class="gb-modal-content">
        <span class="gb-close-modal" onclick="closeMsgModal()">&times;</span>
        <h3 style="margin-top:0; color:#0f172a;">Message <?php the_title(); ?></h3>
        <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Ask a question before you book. We'll notify them immediately.</p>

        <form id="gb-msg-form">
            <input type="hidden" name="action" value="gb_send_message">
            <input type="hidden" name="cleaner_id" value="<?php echo (int) $cleaner_id; ?>">
            <?php wp_nonce_field('gb_msg_nonce', 'security'); ?>

            <?php
            /*
             * Labels were sitting next to their fields but not tied to them,
             * so a screen reader announced three unlabelled boxes. Since the
             * flow now asks people to message a cleaner before booking, this
             * is the form the whole journey runs through.
             *
             * The id carries the cleaner's post id: two profiles could not
             * collide, but a duplicate id would break the association anyway.
             */
            $gb_msg_uid = 'gb-msg-' . (int) $cleaner_id;
            ?>
            <div style="display:none;" aria-hidden="true">
                <input type="text" name="gb_honeypot" value="" autocomplete="off" tabindex="-1">
            </div>

            <label for="<?php echo esc_attr($gb_msg_uid); ?>-name">Your Name</label>
            <input id="<?php echo esc_attr($gb_msg_uid); ?>-name" type="text" name="sender_name" required placeholder="e.g. John Smith" autocomplete="name">

            <label for="<?php echo esc_attr($gb_msg_uid); ?>-email">Your Email</label>
            <input id="<?php echo esc_attr($gb_msg_uid); ?>-email" type="email" name="sender_email" required placeholder="john@example.com" autocomplete="email">

            <label for="<?php echo esc_attr($gb_msg_uid); ?>-body">Message</label>
            <textarea id="<?php echo esc_attr($gb_msg_uid); ?>-body" name="message_body" required placeholder="Hi, do you bring your own vacuum?"></textarea>

            <button type="submit" class="gb-btn-book" style="margin-top:6px;">Send Message</button>
        </form>
        <div id="gb-msg-response" style="margin-top:15px; text-align:center; font-weight:600; font-size:14px;"></div>
    </div>
</div>

<script>
    function openMsgModal() { document.getElementById('gb-msg-modal').style.display = 'flex'; }
    function closeMsgModal() { document.getElementById('gb-msg-modal').style.display = 'none'; }

    jQuery(document).ready(function($){
        $('#gb-msg-form').on('submit', function(e){
            e.preventDefault();
            var btn = $(this).find('button[type=submit]');
            var resp = $('#gb-msg-response');

            btn.text('Sending...').prop('disabled', true);

            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                type: 'POST',
                dataType: 'json',
                data: $(this).serialize(),
                success: function(res) {
                    if(res.success) {
                        if (window.gbFlushTracking) { window.gbFlushTracking(); }
                        $('#gb-msg-form').hide();
                        resp.html('<span style="color:#2e7d32;">✅ ' + res.data.message + '</span>');
                        setTimeout(closeMsgModal, 3000);
                    } else {
                        resp.html('<span style="color:#ef4444;">⚠️ ' + res.data.message + '</span>');
                        btn.text('Send Message').prop('disabled', false);
                    }
                },
                error: function() {
                    resp.html('<span style="color:#ef4444;">❌ Server Error. Please try again.</span>');
                    btn.text('Send Message').prop('disabled', false);
                }
            });
        });
    });
</script>

<?php get_footer(); ?>
