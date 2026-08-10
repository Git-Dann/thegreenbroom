<?php
/**
 * Template Name: Legal Page (Text Heavy)
 * Description: A clean, centered template designed for Privacy, Terms, and GDPR content.
 */

get_header();

$legal_slug = get_post_field('post_name', get_the_ID());
$contact_url = function_exists('gb_get_contact_page_url') ? gb_get_contact_page_url() : home_url('/contact/');
$legal_content_map = [
    'privacy' => [
        'eyebrow' => 'Privacy, Cookies & GDPR',
        'title' => 'Privacy Notice',
        'sections' => [
            [
                'id' => 'privacy-overview',
                'heading' => '1. What We Collect',
                'paragraphs' => [
                    'We collect the information needed to operate The Green Broom, including account details, booking details, support messages, and profile information submitted by cleaners.',
                    'Payment card details are handled by Stripe. We receive transaction and status data needed to confirm bookings, manage refunds, and support payouts, but we do not store full card numbers on our own servers.',
                ],
            ],
            [
                'id' => 'how-we-use-data',
                'heading' => '2. How We Use Your Information',
                'paragraphs' => [
                    'We use personal data to create accounts, match customers with cleaners, manage bookings, provide support, operate verification checks, prevent misuse, and comply with legal obligations.',
                    'Where necessary, we also use operational data to improve platform safety, service quality, and reliability.',
                ],
            ],
            [
                'id' => 'sharing',
                'heading' => '3. Who We Share Data With',
                'paragraphs' => [
                    'We share relevant booking information with the cleaner assigned to a booking, and we share payment-related information with Stripe and other service providers that help us host, secure, and operate the platform.',
                    'We do not sell personal data.',
                ],
            ],
            [
                'id' => 'retention',
                'heading' => '4. Retention',
                'paragraphs' => [
                    'We retain account, booking, and support records for as long as they are reasonably required to operate the service, resolve disputes, satisfy tax and accounting duties, and enforce our terms.',
                ],
            ],
            [
                'id' => 'cookies-policy',
                'heading' => '5. Cookie Policy',
                'paragraphs' => [
                    'We use essential cookies and similar technologies to keep users signed in, protect forms, maintain sessions, and support core site functionality.',
                    'We may also use limited performance or security-related cookies to understand platform reliability and protect against abuse. You can manage cookies through your browser settings, but disabling essential cookies may affect site functionality.',
                ],
            ],
            [
                'id' => 'gdpr-rights',
                'heading' => '6. UK GDPR Rights',
                'paragraphs' => [
                    'Depending on your location and circumstances, you may have the right to request access to your personal data, ask for correction or deletion, object to some processing, or request restriction or portability where applicable.',
                    'To make a privacy request, contact us using the support details below and include enough information for us to verify your request safely.',
                ],
            ],
        ],
    ],
    'terms' => [
        'eyebrow' => 'Platform Terms',
        'title' => 'Terms & Conditions',
        'sections' => [
            [
                'id' => 'acceptance',
                'heading' => '1. Using The Platform',
                'paragraphs' => [
                    'By using The Green Broom, you agree to use the platform lawfully and in line with these terms. You are responsible for providing accurate information when creating an account or making a booking.',
                ],
            ],
            [
                'id' => 'bookings',
                'heading' => '2. Bookings and Payments',
                'paragraphs' => [
                    'Bookings are managed through the platform and may be subject to availability, verification, and payment confirmation. Payments are processed through Stripe and are not confirmed until the relevant transaction status is received.',
                    'Cleaner payouts, fees, and booking status updates may depend on Stripe Connect onboarding and compliance checks.',
                ],
            ],
            [
                'id' => 'cleaner-standards',
                'heading' => '3. Cleaner Standards',
                'paragraphs' => [
                    'Cleaner accounts may be held in review or removed from public listing until required verification information has been provided and approved. The Green Broom may suspend access where information is incomplete, misleading, or raises safeguarding concerns.',
                ],
            ],
            [
                'id' => 'cancellations',
                'heading' => '4. Cancellations and Support',
                'paragraphs' => [
                    'Cancellation handling, booking changes, and support requests are subject to the policies published on the site and any booking-specific terms shown at checkout or within the customer account area.',
                ],
            ],
            [
                'id' => 'liability',
                'heading' => '5. Liability',
                'paragraphs' => [
                    'To the extent permitted by law, The Green Broom is not liable for indirect or consequential losses. Nothing in these terms excludes liability where it cannot legally be excluded.',
                ],
            ],
        ],
    ],
    'subscribers-terms-of-association' => [
        'eyebrow' => 'Subscriber Agreement',
        'title' => "Subscribers' Terms of Association",
        'sections' => function_exists( 'gb_get_subscriber_terms_of_association_sections' ) ? gb_get_subscriber_terms_of_association_sections() : array(),
    ],
];
$legal_content = isset($legal_content_map[$legal_slug]) ? $legal_content_map[$legal_slug] : null;
?>

<style>
    /* Legal Layout Styles */
    .gb-legal-hero { 
        background: #f8fafc; 
        padding: 80px 0 60px; 
        text-align: center; 
        border-bottom: 1px solid #e2e8f0; 
    }
    .gb-legal-container { 
        max-width: 800px; 
        margin: 0 auto; 
        padding: 60px 20px 100px; 
    }
    
    /* Typography for Text-Heavy Content */
    .gb-legal-content {
        font-size: 16px;
        line-height: 1.8;
        color: #334155; /* Slate 700 */
    }
    .gb-legal-content h1, 
    .gb-legal-content h2, 
    .gb-legal-content h3 { 
        color: #0f172a; /* Slate 900 */
        margin-top: 2em;
        margin-bottom: 0.5em;
        letter-spacing: -0.02em;
    }
    .gb-legal-content h2 { font-size: 24px; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    .gb-legal-content h3 { font-size: 18px; font-weight: 700; }
    
    .gb-legal-content p { margin-bottom: 1.5em; }
    
    .gb-legal-content ul, 
    .gb-legal-content ol { 
        margin-bottom: 2em; 
        padding-left: 20px; 
    }
    .gb-legal-content li { margin-bottom: 0.8em; }

    /* Green OS Accent */
    .gb-legal-accent {
        color: #2e7d32;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.1em;
        display: block;
        margin-bottom: 10px;
    }

    .gb-last-updated {
        font-size: 13px;
        color: #94a3b8;
        margin-top: 10px;
        font-style: italic;
    }
</style>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    
    <header class="gb-legal-hero">
        <div class="gb-container">
            <span class="gb-legal-accent">Legal Documentation</span>
            <h1 style="font-size: 42px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -1px;">
                <?php echo esc_html($legal_content['title'] ?? get_the_title()); ?>
            </h1>
            <p class="gb-last-updated">Last Updated: <?php echo get_the_modified_date('F j, Y'); ?></p>
        </div>
    </header>

    <div class="gb-legal-container">
        <div class="gb-legal-content">
            <?php if ($legal_content): ?>
                <p><strong><?php echo esc_html($legal_content['eyebrow']); ?></strong></p>
                <?php foreach ($legal_content['sections'] as $section): ?>
                    <section id="<?php echo esc_attr($section['id']); ?>">
                        <h2><?php echo esc_html($section['heading']); ?></h2>
                        <?php foreach ($section['paragraphs'] as $paragraph): ?>
                            <p><?php echo esc_html($paragraph); ?></p>
                        <?php endforeach; ?>
                        <?php if ( ! empty( $section['items'] ) ) : ?>
                            <ul>
                                <?php foreach ( $section['items'] as $item ) : ?>
                                    <li><?php echo esc_html( $item ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                while ( have_posts() ) :
                    the_post();
                    the_content();
                endwhile;
                ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 60px; padding-top: 40px; border-top: 1px solid #f1f5f9;">
            <?php if ( $legal_slug === 'subscribers-terms-of-association' && function_exists( 'gb_get_resource_url' ) ) : ?>
                <p><a href="<?php echo esc_url( gb_get_resource_url( 'subscribers-terms-of-association' ) ); ?>" target="_blank" rel="noopener" style="color: #2e7d32; font-weight: 700; text-decoration: none;">Download a PDF copy</a></p>
            <?php endif; ?>
            <p style="font-size: 14px; color: #64748b;">
                Have questions about our legal policies? 
                <a href="<?php echo esc_url($contact_url); ?>" style="color: #2e7d32; font-weight: 700; text-decoration: none;">
                    Contact Management &rarr;
                </a>
            </p>
        </div>
    </div>

</article>

<?php get_footer(); ?>
