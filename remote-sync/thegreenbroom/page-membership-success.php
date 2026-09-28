<?php
/*
 * Template Name: Membership Success
 *
 * Where Stripe sends somebody after they have paid.
 *
 * Two things this page has to do, because the first version of the flow did
 * neither: tell them it worked, and tell them what to do next. Dropping
 * somebody back on the panel they just bought from, under a one-line notice,
 * answers neither question.
 *
 * It reads state rather than trusting the URL. Arriving here proves only that
 * Stripe redirected a browser; the membership itself is written by the signed
 * webhook, which can land a second after the browser does. So the page says
 * what it can actually see, and offers a refresh while it cannot see it yet.
 */

get_header();

$gb_plan_key  = isset( $_GET['plan'] ) ? sanitize_key( wp_unslash( $_GET['plan'] ) ) : '';
$gb_catalogue = function_exists( 'gb_subscription_catalogue' ) ? gb_subscription_catalogue() : array();
$gb_plan      = isset( $gb_catalogue[ $gb_plan_key ] ) ? $gb_catalogue[ $gb_plan_key ] : null;
$gb_audience  = $gb_plan ? $gb_plan['audience'] : 'customer';

$gb_user     = wp_get_current_user();
$gb_logged   = $gb_user instanceof WP_User && $gb_user->exists();
$gb_user_id  = $gb_logged ? (int) $gb_user->ID : 0;

// What the site currently believes, which is the only thing worth printing.
$gb_confirmed = false;
$gb_renews    = '';

if ( $gb_user_id ) {
    if ( $gb_audience === 'cleaner' ) {
        $gb_confirmed = (string) get_user_meta( $gb_user_id, '_gb_listing_status', true ) === 'active';
        $gb_renews    = (string) get_user_meta( $gb_user_id, '_gb_listing_renews', true );
    } elseif ( function_exists( 'gb_get_membership' ) ) {
        $gb_membership = gb_get_membership( $gb_user_id );
        $gb_confirmed  = $gb_membership['status'] === 'active';
        $gb_renews     = (string) $gb_membership['expires'];
    }
}

$gb_renews_display = '';

if ( $gb_renews !== '' && strtotime( $gb_renews ) ) {
    $gb_renews_display = date_i18n( 'j F Y', strtotime( $gb_renews ) );
}

if ( $gb_audience === 'cleaner' ) {
    $gb_heading = $gb_confirmed ? 'Your free three months has started' : 'Thank you — your listing is being set up';
    $gb_lead    = $gb_confirmed
        ? 'There is nothing to pay today. We will remind you before your free period ends.'
        : 'Your payment details are with Stripe. This page will show your start date once it comes through.';
    $gb_actions = array(
        array( 'Finish your profile', home_url( '/edit-profile' ), true ),
        array( 'Go to your dashboard', home_url( '/cleaner-dashboard' ), false ),
    );
    $gb_next = array(
        'Complete your profile — a photo, your areas and your hourly rate.',
        'Send us your ID, DBS certificate, two references and your insurance. The Verified badge appears once all four are on file.',
        'Your profile goes live to customers as soon as those checks are done.',
    );
} else {
    $gb_heading = $gb_confirmed ? 'You are a member' : 'Thank you — your membership is being set up';
    $gb_lead    = $gb_confirmed
        ? 'You can book any cleaner on the platform.'
        : 'Your payment is with Stripe. This page will show your membership once it comes through.';
    $gb_actions = array(
        array( 'Find a cleaner', home_url( '/cleaner-listings' ), true ),
        array( 'Your account', home_url( '/my-account' ), false ),
    );
    $gb_next = array(
        'Search your postcode to see the verified cleaners covering your street.',
        'Message a cleaner directly before you book, if you want to ask anything first.',
        'Pay through the platform — no cash on the doorstep.',
    );
}
?>

<main class="gb-success-main">
    <div class="gb-container gb-success-wrap">

        <div class="gb-success-mark<?php echo $gb_confirmed ? '' : ' gb-success-mark-pending'; ?>" aria-hidden="true"><?php echo $gb_confirmed ? '&#10003;' : '&hellip;'; ?></div>

        <h1 class="gb-success-head"><?php echo esc_html( $gb_heading ); ?></h1>
        <p class="gb-success-lead"><?php echo esc_html( $gb_lead ); ?></p>

        <?php if ( $gb_plan || $gb_renews_display !== '' || $gb_logged ) : ?>
            <dl class="gb-success-facts">
                <?php if ( $gb_plan ) : ?>
                    <div><dt>Plan</dt><dd><?php echo esc_html( $gb_plan['label'] ); ?></dd></div>
                <?php endif; ?>
                <?php if ( $gb_renews_display !== '' ) : ?>
                    <div>
                        <dt><?php echo $gb_audience === 'cleaner' ? 'First payment' : 'Renews'; ?></dt>
                        <dd><?php echo esc_html( $gb_renews_display ); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ( $gb_logged ) : ?>
                    <div><dt>Account</dt><dd><?php echo esc_html( $gb_user->user_email ); ?></dd></div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>

        <?php if ( ! $gb_confirmed ) : ?>
            <p class="gb-success-pending">
                Stripe usually confirms within a few seconds.
                <a href="<?php echo esc_url( add_query_arg( array( 'plan' => $gb_plan_key, 'r' => time() ), home_url( '/membership-success/' ) ) ); ?>">Check again</a>.
                Nothing is lost if you leave this page — your receipt is on its way by email.
            </p>
        <?php endif; ?>

        <div class="gb-success-actions">
            <?php foreach ( $gb_actions as $gb_action ) : ?>
                <a class="gb-btn <?php echo $gb_action[2] ? 'gb-btn-primary' : 'gb-btn-outline'; ?>" href="<?php echo esc_url( $gb_action[1] ); ?>"><?php echo esc_html( $gb_action[0] ); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="gb-success-next">
            <h2>What happens next</h2>
            <ol>
                <?php foreach ( $gb_next as $gb_step ) : ?>
                    <li><?php echo esc_html( $gb_step ); ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <p class="gb-success-help">
            Something not right? <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Get in touch</a> and we will sort it.
        </p>
    </div>
</main>

<?php get_footer(); ?>
