<?php
/**
 * Seed copy for the legal pages.
 *
 * Privacy, Terms and the Subscribers' Terms used to be hardcoded inside
 * page-legal.php, which always won over the page's own content - so editing
 * them in the manager dashboard saved successfully and changed nothing on the
 * live site. The copy now lives here for two purposes:
 *
 *   1. A one-time migration writes it into each page's content, after which
 *      the dashboard is the single source of truth and edits take effect.
 *   2. It stays as a render-time fallback for a page whose content is empty,
 *      so a legal page can never go blank - not if the migration failed to
 *      run, and not if someone clears the editor and saves.
 *
 * Because of (2) this file is NOT the live text once a page has been edited.
 * Treat it as the factory default, and edit the real thing in the dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_get_legal_seed_documents() {
    return array(
        'privacy' => array(
            'title' => 'Privacy Notice',
            'html'  => '<p><strong>Privacy, Cookies &amp; GDPR</strong></p><h2 id="privacy-overview">1. What We Collect</h2><p>We collect the information needed to operate The Green Broom, including account details, booking details, support messages, and profile information submitted by cleaners.</p><p>Payment card details are handled by Stripe. We receive transaction and status data needed to confirm bookings, manage refunds, and support payouts, but we do not store full card numbers on our own servers.</p><h2 id="how-we-use-data">2. How We Use Your Information</h2><p>We use personal data to create accounts, match customers with cleaners, manage bookings, provide support, operate verification checks, prevent misuse, and comply with legal obligations.</p><p>Where necessary, we also use operational data to improve platform safety, service quality, and reliability.</p><h2 id="sharing">3. Who We Share Data With</h2><p>We share relevant booking information with the cleaner assigned to a booking, and we share payment-related information with Stripe and other service providers that help us host, secure, and operate the platform.</p><p>We do not sell personal data.</p><h2 id="retention">4. Retention</h2><p>We retain account, booking, and support records for as long as they are reasonably required to operate the service, resolve disputes, satisfy tax and accounting duties, and enforce our terms.</p><h2 id="cookies-policy">5. Cookie Policy</h2><p>We use essential cookies and similar technologies to keep users signed in, protect forms, maintain sessions, and support core site functionality.</p><p>We may also use limited performance or security-related cookies to understand platform reliability and protect against abuse. You can manage cookies through your browser settings, but disabling essential cookies may affect site functionality.</p><h2 id="gdpr-rights">6. UK GDPR Rights</h2><p>Depending on your location and circumstances, you may have the right to request access to your personal data, ask for correction or deletion, object to some processing, or request restriction or portability where applicable.</p><p>To make a privacy request, contact us using the support details below and include enough information for us to verify your request safely.</p>',
        ),
        'terms' => array(
            'title' => 'Terms & Conditions',
            'html'  => '<p><strong>Platform Terms</strong></p><h2 id="acceptance">1. Using The Platform</h2><p>By using The Green Broom, you agree to use the platform lawfully and in line with these terms. You are responsible for providing accurate information when creating an account or making a booking.</p><h2 id="bookings">2. Bookings and Payments</h2><p>Bookings are managed through the platform and may be subject to availability, verification, and payment confirmation. Payments are processed through Stripe and are not confirmed until the relevant transaction status is received.</p><p>Cleaner payouts, fees, and booking status updates may depend on Stripe Connect onboarding and compliance checks.</p><h2 id="cleaner-standards">3. Cleaner Standards</h2><p>Cleaner accounts may be held in review or removed from public listing until required verification information has been provided and approved. The Green Broom may suspend access where information is incomplete, misleading, or raises safeguarding concerns.</p><h2 id="cancellations">4. Cancellations and Support</h2><p>Cancellation handling, booking changes, and support requests are subject to the policies published on the site and any booking-specific terms shown at checkout or within the customer account area.</p><h2 id="liability">5. Liability</h2><p>To the extent permitted by law, The Green Broom is not liable for indirect or consequential losses. Nothing in these terms excludes liability where it cannot legally be excluded.</p>',
        ),
        'subscribers-terms-of-association' => array(
            'title' => 'Subscribers\' Terms of Association',
            'html'  => '<p><strong>Subscriber Agreement</strong></p><h2 id="about-the-green-broom">1. About The Green Broom</h2><p>The Green Broom is a UK eco-friendly cleaner platform that connects homeowners and other members seeking cleaning services with a database of verified and vetted, self-employed cleaners.</p><h2 id="membership">2. Membership</h2><p>Access to The Green Broom database and its member services is available to subscribers who pay the applicable quarterly or annual membership fee. Membership provides exceptional value and remains active for the period for which the subscription has been paid.</p><h2 id="member-benefits">3. Member Benefits</h2><p>While subscribed, members may benefit from:</p><ul><li>Access to the database of verified and vetted self-employed cleaners.</li><li>The ability to make bookings through the platform.</li><li>Cashless payment facilities.</li><li>The ability to leave reviews of cleaners.</li><li>Insurance cover provided by The Green Broom, subject to the applicable terms, conditions and exclusions.</li></ul><h2 id="self-employed-cleaners">4. Self-Employed Cleaners</h2><p>Members acknowledge and agree that all cleaners listed on The Green Broom are self-employed and independent contractors. Cleaners are not employees, agents or representatives of The Green Broom and are not supplied or employed by The Green Broom.</p><h2 id="private-arrangement">5. Private Arrangement Between Member and Cleaner</h2><p>The Green Broom&#039;s role is to provide a platform that enables members and cleaners to find and connect with one another. Once a member chooses a cleaner, the cleaning arrangement is a private agreement between the member and the self-employed cleaner.</p><p>Members are free to contact cleaners directly and, where appropriate, arrange future cleaning services directly with them. The Green Broom does not become a party to such arrangements and does not charge an ongoing commission or fee for cleaning services arranged between members and cleaners.</p><h2 id="responsibility-for-services">6. Responsibility for Cleaning Services</h2><p>The member and cleaner are responsible for agreeing the scope, timing, price and other terms of any cleaning service. The Green Broom does not direct or control how a self-employed cleaner carries out their work.</p><h2 id="platform-role">7. Platform Role</h2><p>The Green Broom provides the platform, database and associated member services but does not itself provide cleaning services. Its role is principally to facilitate connection between members and independent, self-employed cleaners.</p><h2 id="membership-continuation">8. Membership Continuation</h2><p>The benefits of membership, including access to the database and platform services, continue only while a member&#039;s subscription remains active. The cleaner&#039;s subscription must also be active. The Green Broom reserves the right to amend or update these terms from time to time, with reasonable notice where appropriate.</p><h2 id="acceptance-of-terms">9. Acceptance of These Terms</h2><p>By subscribing to The Green Broom, the member confirms that they have read, understood and accepted these Terms of Association, including the fact that cleaners are self-employed and that any cleaning arrangement is a private arrangement between the member and the cleaner.</p>',
        ),
    );
}

/**
 * The factory-default body for a legal slug, or '' if there isn't one.
 */
function gb_get_legal_seed_content( $slug ) {
    $docs = gb_get_legal_seed_documents();
    return isset( $docs[ $slug ] ) ? $docs[ $slug ]['html'] : '';
}

function gb_get_legal_seed_title( $slug ) {
    $docs = gb_get_legal_seed_documents();
    return isset( $docs[ $slug ] ) ? $docs[ $slug ]['title'] : '';
}

