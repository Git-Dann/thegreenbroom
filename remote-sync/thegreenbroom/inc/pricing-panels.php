<?php
/**
 * The membership pricing panels.
 *
 * Two of them: one selling customer membership, shown on the homepage and on
 * Find a Cleaner, and one selling cleaner membership, shown on Become a
 * Cleaner. Both read their wording and prices from the site settings, so the
 * figures live in one place and a price change is one edit rather than three.
 *
 * They now take money, through inc/membership-checkout.php, but only when the
 * plan has been provisioned in Stripe for the active mode. Until then - and
 * for the public while the site is in Test Mode - they fall back to the old
 * behaviour of sending people to registration, so the panel is never offering
 * a purchase that cannot complete.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_pricing_settings_id() {
    if ( function_exists( 'gb_get_site_settings_id' ) ) {
        return (int) gb_get_site_settings_id();
    }

    return (int) get_option( 'page_on_front' );
}

/**
 * Every field both panels use, with the wording that ships if nobody edits it.
 */
function gb_pricing_panel_defaults() {
    return array(
        // Customer membership: homepage and Find a Cleaner.
        '_gb_price_cust_show'      => '1',
        '_gb_price_cust_head'      => 'A better way to find a cleaner',
        '_gb_price_cust_sub'       => 'One low-cost membership to reach every verified cleaner on the platform.',
        '_gb_price_cust_p1_name'   => 'Quarterly',
        '_gb_price_cust_p1_price'  => '£24.95',
        '_gb_price_cust_p1_period' => 'every 3 months',
        '_gb_price_cust_p2_name'   => 'Annual',
        '_gb_price_cust_p2_price'  => '£69.95',
        '_gb_price_cust_p2_period' => 'per year',
        '_gb_price_cust_p2_flag'   => 'Best value',
        '_gb_price_cust_benefits'  => "Verified local cleaners, ID and reference checked\nSecure bookings through the platform\nNo cash on the doorstep\nReal reviews from real bookings\nMember benefits and offers",
        '_gb_price_cust_btn'       => 'Create your account',
        '_gb_price_cust_link'      => '/register',
        '_gb_price_cust_foot'      => 'Browsing is free while we finish setting up payments. Create an account now and we will be in touch before anything is charged.',

        // Cleaner membership: Become a Cleaner.
        '_gb_price_clean_show'     => '1',
        '_gb_price_clean_head'     => 'Your first three months are free',
        '_gb_price_clean_sub'      => 'List your profile, take bookings and keep what you earn.',
        '_gb_price_clean_price'    => '£14.95',
        '_gb_price_clean_period'   => 'per calendar month after that',
        '_gb_price_clean_free'     => 'Free for 3 months',
        '_gb_price_clean_benefits' => "Your own profile, seen by local customers\nVerified badge once your checks are done\nMessages and bookings handled for you\nNo commission taken from your work\nCancel whenever you like",
        '_gb_price_clean_btn'      => 'Become a Cleaner',
        '_gb_price_clean_link'     => '/register',
        '_gb_price_clean_foot'     => 'Nothing to pay today. We will let you know before your free three months are up.',
    );
}

function gb_pricing_field( $key ) {
    $defaults = gb_pricing_panel_defaults();
    $default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
    $id       = gb_pricing_settings_id();

    if ( ! $id ) {
        return $default;
    }

    $stored = get_post_meta( $id, $key, true );

    return ( $stored === '' || $stored === null ) ? $default : $stored;
}

/**
 * Turn a benefits textarea into a list, dropping blank lines so a stray
 * newline does not print an empty bullet.
 */
function gb_pricing_lines( $key ) {
    $raw   = (string) gb_pricing_field( $key );
    $lines = array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) );

    return array_values( array_filter( $lines, static function ( $line ) {
        return $line !== '';
    } ) );
}

function gb_pricing_panel_enabled( $which ) {
    $key = $which === 'cleaner' ? '_gb_price_clean_show' : '_gb_price_cust_show';

    return (string) gb_pricing_field( $key ) !== '0';
}

/**
 * A buy button, or nothing if this plan cannot be bought by this visitor.
 *
 * Posts to the front end rather than admin-post.php: the site redirects
 * non-administrators out of /wp-admin/, which would swallow the click.
 *
 * @return bool whether anything was printed
 */
function gb_pricing_checkout_button( $plan_key, $label ) {
    if ( ! function_exists( 'gb_membership_checkout_visible' ) || ! gb_membership_checkout_visible( $plan_key ) ) {
        return false;
    }

    $test_mode = function_exists( 'gb_get_stripe_config' );
    $config    = $test_mode ? gb_get_stripe_config() : array();
    $test_mode = isset( $config['mode'] ) && $config['mode'] !== 'live';
    ?>
    <form method="post" class="gb-pricing-buy">
        <input type="hidden" name="gb_membership_action" value="checkout">
        <input type="hidden" name="gb_plan" value="<?php echo esc_attr( $plan_key ); ?>">
        <?php wp_nonce_field( 'gb_membership_checkout', 'gb_membership_nonce' ); ?>
        <button type="submit" class="gb-btn gb-btn-primary gb-pricing-btn"><?php echo esc_html( $label ); ?></button>
        <?php if ( $test_mode ) : ?>
            <span class="gb-pricing-testflag">Test mode &mdash; only you can see this button</span>
        <?php endif; ?>
    </form>
    <?php

    return true;
}

/**
 * The message shown when somebody comes back from Stripe.
 */
function gb_pricing_return_notice() {
    if ( function_exists( 'gb_membership_return_notice' ) ) {
        echo gb_membership_return_notice(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped at source.
    }
}

/**
 * @param string $which 'customer' or 'cleaner'
 */
function gb_render_pricing_panel( $which = 'customer' ) {
    if ( ! gb_pricing_panel_enabled( $which ) ) {
        return;
    }

    if ( $which === 'cleaner' ) {
        gb_render_cleaner_pricing_panel();
        return;
    }

    gb_render_customer_pricing_panel();
}

function gb_render_customer_pricing_panel() {
    $benefits = gb_pricing_lines( '_gb_price_cust_benefits' );
    $foot     = trim( (string) gb_pricing_field( '_gb_price_cust_foot' ) );
    $flag     = trim( (string) gb_pricing_field( '_gb_price_cust_p2_flag' ) );
    ?>
    <section class="gb-pricing-panel" id="membership">
        <div class="gb-container">
            <div class="gb-pricing-inner">
                <div class="gb-pricing-pitch">
                    <h2 class="gb-pricing-head"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_head' ) ); ?></h2>
                    <p class="gb-pricing-sub"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_sub' ) ); ?></p>
                    <?php if ( $benefits ) : ?>
                        <ul class="gb-pricing-benefits">
                            <?php foreach ( $benefits as $benefit ) : ?>
                                <li><span aria-hidden="true">&#10003;</span> <?php echo esc_html( $benefit ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="gb-pricing-plans">
                    <?php gb_pricing_return_notice(); ?>
                    <div class="gb-pricing-plan">
                        <span class="gb-pricing-plan-name"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_p1_name' ) ); ?></span>
                        <span class="gb-pricing-plan-price"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_p1_price' ) ); ?></span>
                        <span class="gb-pricing-plan-period"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_p1_period' ) ); ?></span>
                        <?php $bought_p1 = gb_pricing_checkout_button( 'customer_quarterly', 'Choose ' . gb_pricing_field( '_gb_price_cust_p1_name' ) ); ?>
                    </div>
                    <div class="gb-pricing-plan gb-pricing-plan-featured">
                        <?php if ( $flag !== '' ) : ?>
                            <span class="gb-pricing-flag"><?php echo esc_html( $flag ); ?></span>
                        <?php endif; ?>
                        <span class="gb-pricing-plan-name"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_p2_name' ) ); ?></span>
                        <span class="gb-pricing-plan-price"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_p2_price' ) ); ?></span>
                        <span class="gb-pricing-plan-period"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_p2_period' ) ); ?></span>
                        <?php $bought_p2 = gb_pricing_checkout_button( 'customer_annual', 'Choose ' . gb_pricing_field( '_gb_price_cust_p2_name' ) ); ?>
                    </div>

                    <?php if ( empty( $bought_p1 ) && empty( $bought_p2 ) ) : ?>
                        <a class="gb-btn gb-btn-primary gb-pricing-btn" href="<?php echo esc_url( gb_pricing_field( '_gb_price_cust_link' ) ); ?>"><?php echo esc_html( gb_pricing_field( '_gb_price_cust_btn' ) ); ?></a>
                    <?php endif; ?>

                    <?php if ( $foot !== '' ) : ?>
                        <p class="gb-pricing-foot"><?php echo esc_html( $foot ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php
}

function gb_render_cleaner_pricing_panel() {
    $benefits = gb_pricing_lines( '_gb_price_clean_benefits' );
    $foot     = trim( (string) gb_pricing_field( '_gb_price_clean_foot' ) );
    $free     = trim( (string) gb_pricing_field( '_gb_price_clean_free' ) );
    ?>
    <section class="gb-pricing-panel" id="membership">
        <div class="gb-container">
            <div class="gb-pricing-inner">
                <div class="gb-pricing-pitch">
                    <h2 class="gb-pricing-head"><?php echo esc_html( gb_pricing_field( '_gb_price_clean_head' ) ); ?></h2>
                    <p class="gb-pricing-sub"><?php echo esc_html( gb_pricing_field( '_gb_price_clean_sub' ) ); ?></p>
                    <?php if ( $benefits ) : ?>
                        <ul class="gb-pricing-benefits">
                            <?php foreach ( $benefits as $benefit ) : ?>
                                <li><span aria-hidden="true">&#10003;</span> <?php echo esc_html( $benefit ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="gb-pricing-plans">
                    <?php gb_pricing_return_notice(); ?>
                    <div class="gb-pricing-plan gb-pricing-plan-featured">
                        <?php if ( $free !== '' ) : ?>
                            <span class="gb-pricing-flag"><?php echo esc_html( $free ); ?></span>
                        <?php endif; ?>
                        <span class="gb-pricing-plan-price"><?php echo esc_html( gb_pricing_field( '_gb_price_clean_price' ) ); ?></span>
                        <span class="gb-pricing-plan-period"><?php echo esc_html( gb_pricing_field( '_gb_price_clean_period' ) ); ?></span>
                    </div>

                    <?php if ( ! gb_pricing_checkout_button( 'cleaner_monthly', gb_pricing_field( '_gb_price_clean_btn' ) ) ) : ?>
                        <a class="gb-btn gb-btn-primary gb-pricing-btn" href="<?php echo esc_url( gb_pricing_field( '_gb_price_clean_link' ) ); ?>"><?php echo esc_html( gb_pricing_field( '_gb_price_clean_btn' ) ); ?></a>
                    <?php endif; ?>

                    <?php if ( $foot !== '' ) : ?>
                        <p class="gb-pricing-foot"><?php echo esc_html( $foot ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php
}
