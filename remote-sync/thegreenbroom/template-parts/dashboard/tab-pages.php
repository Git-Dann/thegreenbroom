<?php
$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
$selected_page_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$edit_page = null;

if ($action === 'edit' && $selected_page_id) {
    $candidate = get_post($selected_page_id);
    if ($candidate && $candidate->post_type === 'page') {
        $edit_page = $candidate;
    }
}

$invalid_edit_target = ($action === 'edit' && $selected_page_id && !$edit_page);
$gb_structured_core_slugs = ['home', 'how-it-works', 'how-we-do-it', 'what-we-do', 'cleaner-listings', 'join', 'contact', 'contact-support', 'booking-success', 'book-now'];
?>

<style>
    /* Green OS Page Builder UI Tokens */
    .gb-builder-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 25px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
    .gb-builder-layout { display: grid; grid-template-columns: 280px 1fr; gap: 30px; align-items: start; }
    .gb-nav-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; position: sticky; top: 20px; }
    .gb-editor-section { background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px; overflow: hidden; }
    .gb-editor-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 12px; color: #2e7d32; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
    .gb-editor-header .dashicons { font-size: 18px; color: #2e7d32; }
    .gb-editor-body { padding: 25px; }
    .gb-form-row { margin-bottom: 20px; }
    .gb-label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .gb-input, .gb-textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; }
    .gb-textarea { min-height: 70px; resize: vertical; }
    .gb-upload-wrapper { margin-top: 5px; }
    .gb-media-field { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 1fr); gap: 16px; align-items: stretch; }
    .gb-media-controls { min-width: 0; }
    .gb-media-toolbar { display: flex; align-items: stretch; gap: 8px; min-width: 0; }
    .gb-media-input-row { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 auto; min-height: 46px; padding: 0 12px; border: 1px solid #dbe4ef; border-radius: 9px; background: #fff; }
    .gb-media-input-row:focus-within { border-color: #2e7d32; box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1); }
    .gb-media-input-row .dashicons { flex: 0 0 auto; color: #2e7d32; }
    .gb-media-input { min-width: 0; width: 100%; padding: 0; overflow: hidden; border: 0; outline: 0; background: transparent; color: #334155; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .gb-media-input::placeholder { color: #94a3b8; }
    .gb-media-actions { display: flex; flex: 0 0 auto; align-items: stretch; gap: 8px; margin-top: 0; }
    .gb-media-actions .gb-btn-small-os { white-space: nowrap; }
    .gb-media-actions .gb-upload-btn { border-color: #2e7d32; color: #2e7d32; }
    .gb-media-actions .gb-upload-btn:hover { border-color: #256b2a; background: #f0fdf4; }
    .gb-media-clear { display: none; }
    .gb-media-field.has-value .gb-media-clear { display: inline-flex; }
    .gb-media-help { margin: 8px 0 0; color: #94a3b8; font-size: 11px; }
    .gb-media-preview { display: flex; align-items: center; justify-content: center; min-height: 150px; overflow: hidden; border: 1px solid #dbe4ef; border-radius: 9px; background: #f8fafc; }
    .gb-media-preview img { display: block; width: 100%; height: 100%; min-height: 150px; max-height: 220px; object-fit: cover; }
    .gb-media-preview.is-empty::before { content: '\f128'; font-family: dashicons; color: #cbd5e1; font-size: 24px; }
    .gb-media-preview.is-error::before { content: '\f534'; font-family: dashicons; color: #f59e0b; font-size: 24px; }
    @media (max-width: 900px) { .gb-media-field { grid-template-columns: 1fr; } .gb-media-preview { min-height: 180px; } .gb-media-preview img { min-height: 180px; max-height: 240px; } }
    @media (max-width: 620px) { .gb-media-toolbar { flex-direction: column; } .gb-media-actions { width: 100%; } .gb-media-actions .gb-btn-small-os { flex: 1 1 0; justify-content: center; } }
    .gb-link-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .gb-sub-head { font-size: 11px; font-weight: 800; color: #2e7d32; margin: 25px 0 10px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }
    
    .gb-table { width: 100%; border-collapse: collapse; }
    .gb-table th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .gb-table td { padding: 20px 15px; border-bottom: 1px solid #f1f5f9; }

    .gb-btn-small-os { 
        text-decoration: none; padding: 8px 16px; border: 1px solid #e2e8f0; border-radius: 6px; 
        color: #334155; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; 
        transition: all 0.2s ease; background: white; cursor: pointer;
    }
    .gb-btn-small-os:hover { background: #f8fafc; border-color: #cbd5e1; }

    .gb-pages-accordion { margin-top: 28px; overflow: hidden; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
    .gb-pages-accordion > summary { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; color: #64748b; cursor: pointer; list-style: none; }
    .gb-pages-accordion > summary::-webkit-details-marker { display: none; }
    .gb-pages-accordion > summary:hover { background: #f8fafc; }
    .gb-pages-accordion-title { font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
    .gb-pages-accordion-meta { display: inline-flex; align-items: center; gap: 8px; margin-left: 8px; color: #94a3b8; font-size: 12px; font-weight: 600; text-transform: none; letter-spacing: 0; }
    .gb-pages-accordion-count { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 800; }
    .gb-pages-accordion-chevron { transition: transform 0.2s ease; }
    .gb-pages-accordion[open] .gb-pages-accordion-chevron { transform: rotate(180deg); }
    .gb-pages-accordion-body { overflow-x: auto; border-top: 1px solid #e2e8f0; }
</style>

<?php if ($edit_page): 
    $pid = (int) $edit_page->ID;
    $p_obj = $edit_page;
    $slug = $p_obj->post_name;
    $is_front = (((int) get_option('page_on_front') === $pid) || $slug === 'home');
    $is_structured_core_page = in_array($slug, $gb_structured_core_slugs, true) || $is_front;

    $gb_page_defaults = [];
    if ($is_front) {
        $gb_page_defaults = [
            '_gb_hero_bg'       => 'https://images.unsplash.com/photo-1581578731117-10d52143b1e8?auto=format&fit=crop&w=1920&q=80',
            '_gb_hero_headline' => 'Spotless Homes, Cleaner Planet.',
            '_gb_hero_sub'      => "The UK's trusted platform for finding eco-friendly cleaners.",
            '_gb_hero_btn_1'    => 'Find a Cleaner',
            '_gb_hero_link_1'   => '/cleaner-listings',
            '_gb_hero_btn_2'    => 'Become a Cleaner',
            '_gb_hero_link_2'   => '/join',
            '_gb_trust_banner'  => 'Trusted by 500+ Homes Across the UK • 100% Vetted Cleaners',
            '_gb_testi_head'    => 'Meet some of our cleaners',
            '_gb_testi_sub'     => 'Every one of them ID-checked, DBS-checked and reference-checked before they appear here.',
            '_gb_testi_btn'     => 'Browse all cleaners',
            '_gb_testi_link'    => '/cleaner-listings',
            '_gb_split_head'    => "Clean homes shouldn't cost the Earth.",
            '_gb_split_body'    => 'Traditional cleaning products are full of harsh chemicals that can harm your family and pets. Plus, finding a reliable cleaner is often a headache of texts and cash payments.',
            '_gb_split_img'     => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png',
            '_gb_split_list_1'  => '100% Eco-Friendly Products Only',
            '_gb_split_list_2'  => 'Vetted & Verified Professionals',
            '_gb_split_list_3'  => 'Secure Cashless Payments',
            '_gb_split_list_4'  => 'Satisfaction Guaranteed',
            '_gb_hiw_head'      => 'How It Works',
            '_gb_hiw_sub'       => 'Booking a cleaner has never been this simple or sustainable.',
            '_gb_step1_title'   => 'Search & Filter',
            '_gb_step1_text'    => 'Enter your postcode and browse profiles of vetted eco-cleaners in your area.',
            '_gb_step2_title'   => 'Book Securely',
            '_gb_step2_text'    => 'Select a time that works for you and pay securely through the platform.',
            '_gb_step3_title'   => 'Relax & Enjoy',
            '_gb_step3_text'    => "Come home to a spotless house knowing you've made a green choice.",
            '_gb_feat_head'     => 'Why The Green Broom?',
            '_gb_feat1_title'   => 'Eco-Friendly',
            '_gb_feat1_text'    => 'Every cleaner uses non-toxic, sustainable products safe for pets and kids.',
            '_gb_feat2_title'   => 'Fully Vetted',
            '_gb_feat2_text'    => 'We check IDs and references personally. Safety is our #1 priority.',
            '_gb_feat3_title'   => 'Direct Chat',
            '_gb_feat3_text'    => 'Message your cleaner directly to discuss specific needs or access details.',
            '_gb_testi_head'    => 'What our customers say',
            '_gb_testi1_text'   => 'Finally a cleaning service that aligns with my values. My house smells fresh without the chemical smell!',
            '_gb_testi1_auth'   => '— Sarah J., Manchester',
            '_gb_testi2_text'   => 'Booking was so easy. Mike was professional, on time, and did an amazing job on the kitchen.',
            '_gb_testi2_auth'   => '— David P., Leeds',
            '_gb_cta_heading'   => 'Ready to make money cleaning?',
            '_gb_cta_sub'       => 'Join The Green Broom today.',
            '_gb_cta_btn_1'     => 'Get Started Now',
            '_gb_cta_link_1'    => '/register',
            '_gb_cta_btn_2'     => 'Learn More',
            '_gb_cta_link_2'    => '/how-it-works',
            '_gb_mid_img'       => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/rectangle-1000x500-1.png',
        ];
    } elseif ($slug === 'how-it-works') {
        $gb_page_defaults = [
            '_gb_hiw_hero_title' => 'Simple. Secure. Sustainable.',
            '_gb_hiw_hero_sub'   => 'See how The Green Broom makes cleaning better for everyone.',
            '_gb_hiw_s1_title'   => 'Search & Filter',
            '_gb_hiw_s1_desc'    => 'Enter your postcode and browse profiles. Filter by price, reviews, and specific eco-services.',
            '_gb_hiw_s1_img'     => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/rectangle-1000x500-1.png',
            '_gb_hiw_s2_title'   => 'Ask the cleaner a question first',
            '_gb_hiw_s2_desc'    => 'Message the cleaner through the platform to check they cover your area, agree what you need doing, and settle a price before anything is booked.',
            '_gb_hiw_s2_img'     => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png',
            '_gb_hiw_s3_title'   => 'Secure Payment',
            '_gb_hiw_s3_desc'    => 'Pay securely through the platform using Stripe. Funds are held safely and only released when the job is done.',
            '_gb_hiw_s3_img'     => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/rectangle-1000x500-1.png',
            '_gb_hiw_cl_head'    => 'Earn More on Your Terms',
            '_gb_hiw_cl_s1_title'=> '1. Create Profile',
            '_gb_hiw_cl_s1_desc' => 'Showcase your experience, set your rates, and upload photos.',
            '_gb_hiw_cl_s2_title'=> '2. Get Verified',
            '_gb_hiw_cl_s2_desc' => 'Pass our quick ID check to get the Verified badge.',
            '_gb_hiw_cl_s3_title'=> '3. Accept Jobs',
            '_gb_hiw_cl_s3_desc' => 'Get booking requests and accept the ones that fit your schedule.',
            '_gb_hiw_cl_s4_title'=> '4. Earn More',
            '_gb_hiw_cl_s4_desc' => 'Earn more from eco-cleaning with loyal clients who share your values.',
            '_gb_hiw_cl_btn'    => 'Become a Cleaner',
            '_gb_hiw_cl_link'   => '/register',
            '_gb_hiw_faq1_q'     => 'Are the cleaning products safe?',
            '_gb_hiw_faq1_a'     => 'Yes! All cleaners on our platform are committed to using non-toxic, eco-friendly products.',
            '_gb_hiw_faq2_q'     => 'How do I pay?',
            '_gb_hiw_faq2_a'     => 'We use a secure cashless system. You pay via credit/debit card when you book.',
            '_gb_hiw_faq3_q'     => 'Are cleaners vetted?',
            '_gb_hiw_faq3_a'     => 'Absolutely. We check ID and references for every professional to ensure they meet our high standards.',
            '_gb_hiw_faq4_q'     => 'What if I need to cancel?',
            '_gb_hiw_faq4_a'     => 'You can cancel or reschedule up to 24 hours before your booking for a full refund.',
            '_gb_hiw_cta_title'  => 'Ready to get started?',
            '_gb_hiw_cta_desc'   => "Join the community that's cleaning up the UK, one home at a time.",
            '_gb_hiw_cta_btn'    => 'Find a Cleaner',
            '_gb_hiw_cta_link'   => '/cleaner-listings',
        ];
    } elseif ($slug === 'how-we-do-it') {
        $gb_page_defaults = [
            '_gb_about_hero_bg'    => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1920&q=80',
            '_gb_about_hero_title' => 'Cleaning Up The Cleaning Industry.',
            '_gb_about_hero_sub'   => 'We believe in fair pay, non-toxic products, and a spotless finish. No compromises.',
            '_gb_story_head'       => 'Why We Started',
            '_gb_story_lead'       => 'Finding a reliable cleaner used to be a gamble. Finding one who cared about the environment was nearly impossible.',
            '_gb_story_body'       => "We built The Green Broom to solve two massive problems: homeowners were tired of harsh chemicals and unreliable service, and cleaners were tired of low wages and poor treatment.\n\nBy cutting out the expensive agencies and using smart technology, we connect you directly.",
            '_gb_story_img'        => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png',
            '_gb_stat1_num'        => '100%',
            '_gb_stat1_label'      => 'Eco-Friendly',
            '_gb_stat2_num'        => '£15+',
            '_gb_stat2_label'      => 'Avg. Hourly Wage',
            '_gb_stat3_num'        => '0',
            '_gb_stat3_label'      => 'Hidden Fees',
            '_gb_vet_head'         => 'Trust is Everything.',
            '_gb_vet_sub'          => 'We invite less than 10% of applicants to join our platform. Here is how we ensure only the best enter your home.',
            '_gb_vet_s1_title'     => 'ID & Right to Work Checks',
            '_gb_vet_s1_desc'      => 'We verify government-issued ID and proof of address for every single applicant.',
            '_gb_vet_s2_title'     => 'Reference Verification',
            '_gb_vet_s2_desc'      => "We speak to previous employers or clients. If they haven't delivered 5-star service before, they won't be on The Green Broom.",
            '_gb_vet_s3_title'     => 'The "Green" Interview',
            '_gb_vet_s3_desc'      => 'We test their knowledge of eco-friendly cleaning methods.',
            '_gb_vet_s4_title'     => 'Ongoing Monitoring',
            '_gb_vet_s4_desc'      => "Verification doesn't stop at signup. We monitor ratings after every job.",
            '_gb_about_cta_head'   => 'Join the Cleaning Revolution',
            '_gb_about_cta_sub'    => 'Whether you want a spotless home or a flexible career.',
            '_gb_about_btn1_text'  => 'Find a Cleaner',
            '_gb_about_btn1_link'  => '/cleaner-listings',
            '_gb_about_btn2_text'  => 'Become a Cleaner',
            '_gb_about_btn2_link'  => '/join',
        ];
    } elseif ($slug === 'what-we-do') {
        $gb_page_defaults = [
            '_gb_svc_hero_title' => 'More Than Just a Clean Home',
            '_gb_svc_hero_sub'   => 'Professional, reliable, and 100% eco-friendly services tailored to your lifestyle.',
            '_gb_svc_head'       => 'Our Cleaning Services',
            '_gb_svc_sub'        => 'Choose the level of clean that fits your needs. All services use plant-based, non-toxic products.',
            '_gb_svc_1_title'    => 'Weekly Cleaning',
            '_gb_svc_1_desc'     => 'A regular visit to keep your home fresh week to week.',
            '_gb_svc_1_list'     => "Dusting & wiping surfaces\nVacuuming & mopping floors\nKitchen counters\nBathroom sanitation\nEmptying bins",
            '_gb_svc_1_btn'      => 'Find Weekly Cleaners',
            '_gb_svc_1_link'     => '/cleaner-listings/?service=weekly-cleaning',
            '_gb_svc_2_title'    => 'Fortnightly Cleaning',
            '_gb_svc_2_desc'     => 'The same thorough clean, every other week.',
            '_gb_svc_2_list'     => "Dusting & wiping surfaces\nVacuuming & mopping floors\nKitchen counters\nBathroom sanitation\nEmptying bins",
            '_gb_svc_2_btn'      => 'Find Fortnightly Cleaners',
            '_gb_svc_2_link'     => '/cleaner-listings/?service=fortnightly-cleaning',
            '_gb_svc_3_title'    => 'Deep Clean',
            '_gb_svc_3_desc'     => 'A thorough top-to-bottom refresh. Ideal for spring cleaning.',
            '_gb_svc_3_list'     => "Everything in a regular clean\nInside oven & fridge\nSkirting boards\nLimescale removal\nBehind furniture",
            '_gb_svc_3_btn'      => 'Find Deep Cleaners',
            '_gb_svc_3_link'     => '/cleaner-listings/?service=deep-clean',
            '_gb_svc_4_title'    => 'House Move & End of Tenancy',
            '_gb_svc_4_desc'     => 'An intensive clean for an empty property, to help secure your deposit.',
            '_gb_svc_4_list'     => "Deep clean of all rooms\nInside cupboards\nWindow cleaning (interior)\nAppliance deep cleans\nSpot wall cleaning",
            '_gb_svc_4_btn'      => 'Find Move Cleaners',
            '_gb_svc_4_link'     => '/cleaner-listings/?service=house-move',
            '_gb_svc_mis_img'    => 'https://thegreenbroom.uk/wp-content/uploads/2025/12/square-500x500-1.png',
            '_gb_svc_mis_title'  => 'A cleaner home, a greener choice',
            '_gb_svc_mis_sub'    => "We believe a clean home shouldn't come at the cost of the environment.",
            '_gb_svc_mis_body'   => "Traditional cleaning supplies are often packed with volatile organic compounds (VOCs).\n\nWe mandate that every cleaner uses non-toxic, biodegradable products.",
            '_gb_svc_mis_btn'    => 'Join Our Mission',
            '_gb_svc_mis_link'   => '/join',
            '_gb_svc_cta_title'  => 'Ready to book?',
            '_gb_svc_cta_sub'    => 'Find a vetted professional in your area today.',
            '_gb_svc_cta_btn'    => 'Browse Cleaners',
            '_gb_svc_cta_link'   => '/cleaner-listings',
        ];
    } elseif ($slug === 'cleaner-listings') {
        $gb_page_defaults = [
            '_gb_listings_title' => 'Find Your Professional',
            '_gb_listings_sub'   => 'Find trusted local cleaners by entering your postcode.',
            '_gb_listings_none'  => 'No professionals found. Try adjusting your search criteria.',
            '_gb_listings_eyebrow'     => '',
            '_gb_listings_search_head' => 'Search cleaners near you',
            '_gb_listings_ph_postcode' => 'Search by postcode...',
            '_gb_listings_any_service' => 'Any Service',
            '_gb_listings_search_btn'  => 'Search',
            '_gb_listings_results'     => 'Found {count} results for "{term}"',
            '_gb_listings_btn_profile' => 'View Profile',
            '_gb_listings_btn_book'    => 'Book Now',
            '_gb_listings_badge'       => 'Verified',
            '_gb_listings_verified_link'  => 'What does Verified mean?',
            '_gb_listings_verified_title' => '🍃 Green Broom Verified',
            '_gb_listings_verified_card'  => '1',
            '_gb_listings_btn_1'  => '',
            '_gb_listings_link_1' => '#gb-dir-search-head',
            '_gb_listings_btn_2'  => '',
            '_gb_listings_link_2' => '/how-it-works',
            '_gb_listings_hiw_head'     => 'Booking a cleaner takes two minutes',
            '_gb_listings_why_head'     => 'Why book through The Green Broom',
            '_gb_listings_reviews_head' => 'What customers say',
            '_gb_listings_cta_title'    => 'Ready for a cleaner home?',
            '_gb_listings_cta_sub'      => 'Find a verified, eco-friendly cleaner covering your postcode today.',
            '_gb_listings_cta_btn'      => 'Search cleaners near you',
            '_gb_listings_cta_link'     => '#gb-dir-search-head',
        ];
    } elseif ($slug === 'join') {
        $gb_page_defaults = [
            '_gb_join_hero_bg'    => 'https://images.unsplash.com/photo-1581578731117-10d52143b1e8?auto=format&fit=crop&w=1920&q=80',
            '_gb_join_hero_title' => 'Turn cleaning into a career you control.',
            '_gb_join_hero_sub'   => 'Set your own rates, choose your own hours, and keep more of what you earn. The Green Broom connects you directly with vetted local customers — no agency taking the biggest cut.',
            '_gb_join_hero_btn'   => 'Create Your Free Profile',
            '_gb_join_hero_link'  => '/register',
            '_gb_join_hero_btn2'  => 'See How It Works',
            '_gb_join_hero_link2' => '/how-it-works',
            '_gb_join_ben_head'   => 'Why cleaners choose The Green Broom',
            '_gb_join_ben_sub'    => 'We built this platform for cleaners, not agencies. Here is what that means for you.',
            '_gb_join_b1_title'   => 'Keep more of what you earn',
            '_gb_join_b1_text'    => 'No expensive agency middlemen. You set your rates and take home the lion\'s share of every job.',
            '_gb_join_b2_title'   => 'Work on your own terms',
            '_gb_join_b2_text'    => 'Accept only the jobs that fit your schedule. Full-time, part-time, or the odd weekend — it is entirely up to you.',
            '_gb_join_b3_title'   => 'Get paid securely, on time',
            '_gb_join_b3_text'    => 'Customers pay through the platform, so no chasing cash or awkward conversations. Your money lands in your account automatically.',
            '_gb_join_b4_title'   => 'Real customers, ready to book',
            '_gb_join_b4_text'    => 'We bring vetted local homeowners to you. Build your reputation with reviews and let repeat bookings roll in.',
            '_gb_join_stat1_num'   => '£15+',
            '_gb_join_stat1_label' => 'Average hourly earnings',
            '_gb_join_stat2_num'   => '0%',
            '_gb_join_stat2_label' => 'Hidden agency fees',
            '_gb_join_stat3_num'   => 'You',
            '_gb_join_stat3_label' => 'Choose your own hours',
            '_gb_join_steps_head' => 'Start earning in three simple steps',
            '_gb_join_s1_title'   => '1. Create your profile',
            '_gb_join_s1_desc'    => 'Sign up in minutes. Add your experience, set your rates, and showcase your best work with photos.',
            '_gb_join_s2_title'   => '2. Get verified',
            '_gb_join_s2_desc'    => 'Pass our quick ID and reference check to earn your Verified badge — the mark customers trust.',
            '_gb_join_s3_title'   => '3. Accept jobs & get paid',
            '_gb_join_s3_desc'    => 'Receive booking requests from local customers, accept the ones that suit you, and get paid securely.',
            '_gb_join_testi_text' => 'I left my agency job and doubled my take-home pay within two months. I pick my own clients, my own hours, and I actually get to keep what I earn.',
            '_gb_join_testi_auth' => '— Emma R., Cleaner in Bristol',
            '_gb_join_cta_title'  => 'Ready to be your own boss?',
            '_gb_join_cta_sub'    => 'Join hundreds of cleaners already earning more on their own terms. It is free to create your profile.',
            '_gb_join_cta_btn'    => 'Get Started — It\'s Free',
            '_gb_join_cta_link'   => '/register',
        ];
    } elseif ($slug === 'eco-products') {
        // The cards themselves now live in the Eco Products tab as their own
        // records, so only the page-level intro is edited here.
        $gb_page_defaults = [
            '_gb_eco_hero_sub' => 'Practical guidance for choosing and using effective, lower-impact cleaning products at home and on every job.',
        ];
    } elseif ($slug === 'contact') {
        $gb_page_defaults = [
            '_gb_contact_title'         => 'Contact',
            '_gb_contact_sub'           => 'Send a message directly to our management team.',
            '_gb_contact_phone_label'   => 'Phone support:',
            '_gb_contact_hours'         => 'Monday to Friday, 9 am to 5 pm',
            '_gb_contact_label_name'    => 'Your Name',
            '_gb_contact_label_email'   => 'Email Address',
            '_gb_contact_label_subject' => 'Subject',
            '_gb_contact_ph_subject'    => 'How can we help?',
            '_gb_contact_label_message' => 'Message',
            '_gb_contact_btn'           => 'Send Message',
            '_gb_contact_success'       => 'Success! Your message has been sent to our team.',
            '_gb_contact_error'         => 'Please complete all fields with a valid email address.',
        ];
    } elseif ($slug === 'contact-support') {
        $gb_page_defaults = [
            '_gb_csup_back'          => 'Back to Dashboard',
            '_gb_csup_title'         => 'Contact Support',
            '_gb_csup_sub'           => 'Having trouble? Our team in Liverpool is here to help.',
            '_gb_csup_phone_label'   => 'Phone support:',
            '_gb_csup_hours'         => 'Monday to Friday, 9 am to 5 pm',
            '_gb_csup_btn'           => 'Send Message',
            '_gb_csup_success_title' => 'Message sent!',
            '_gb_csup_success_body'  => "We'll be in touch shortly.",
            '_gb_csup_error_title'   => 'Please check your details.',
            '_gb_csup_error_body'    => 'All fields are required, including a valid email.',
        ];
    } elseif ($slug === 'book-now') {
        $gb_page_defaults = [
            '_gb_bk_title'          => 'Complete your Booking',
            '_gb_bk_label_service'  => 'Service Type',
            '_gb_bk_label_duration' => 'Duration',
            '_gb_bk_label_date'     => 'Date',
            '_gb_bk_label_time'     => 'Start Time',
            '_gb_bk_time_help'      => 'Choose a start time that suits you.',
            '_gb_bk_label_name'     => 'Full Name',
            '_gb_bk_label_phone'    => 'Phone Number',
            '_gb_bk_label_email'    => 'Email Address',
            '_gb_bk_label_address'  => 'Property Address',
            '_gb_bk_submit'         => 'Continue to Secure Payment',
            '_gb_bk_note'           => 'You will be redirected to Stripe to pay securely and confirm your booking.',
        ];
    } elseif ($slug === 'booking-success') {
        $gb_page_defaults = [
            '_gb_bs_paid_title'    => 'Booking Confirmed!',
            '_gb_bs_pending_title' => 'Payment Pending',
            '_gb_bs_thanks'        => 'Thanks, {name}.',
            '_gb_bs_account_sub'   => 'Create a password to manage this appointment, reschedule, or cancel anytime.',
            '_gb_bs_account_btn'   => 'Create Account & View Dashboard',
            '_gb_bs_not_found'     => 'No Booking Found',
        ];
    }

    $gb_field_value = function($meta_key, $fallback = '') use ($pid, $gb_page_defaults) {
        if (empty($meta_key)) {
            return $fallback;
        }

        $stored = get_post_meta($pid, $meta_key, true);
        if ($stored !== '' && $stored !== null) {
            return $stored;
        }

        if ($meta_key === '_gb_trust_banner') {
            return 'Trusted by 500+ Homes Across the UK • 100% Vetted Cleaners';
        }

        if (array_key_exists($meta_key, $gb_page_defaults)) {
            return $gb_page_defaults[$meta_key];
        }

        return $fallback;
    };

    $gb_render_media_field = static function($field_name, $label, $value) {
        $field_value = is_scalar($value) ? (string) $value : '';
        ?>
        <div class="gb-form-row">
            <label class="gb-label"><?php echo esc_html($label); ?></label>
            <div class="gb-upload-wrapper">
                <div class="gb-media-field<?php echo $field_value !== '' ? ' has-value' : ''; ?>" data-media-field>
                    <div class="gb-media-controls">
                        <div class="gb-media-toolbar">
                            <div class="gb-media-input-row">
                                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                <input type="text" name="<?php echo esc_attr($field_name); ?>" class="gb-media-input" value="<?php echo esc_attr($field_value); ?>" placeholder="No image selected" title="<?php echo esc_attr($field_value); ?>">
                            </div>
                            <div class="gb-media-actions">
                                <button type="button" class="gb-upload-btn gb-btn-small-os"><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span> Choose image</button>
                                <button type="button" class="gb-media-clear gb-btn-small-os">Clear</button>
                            </div>
                        </div>
                        <p class="gb-media-help">Choose an image from the WordPress media library. Hover over the field to see the full URL.</p>
                    </div>
                    <div class="gb-media-preview<?php echo $field_value === '' ? ' is-empty' : ''; ?>" data-media-preview>
                        <?php if ($field_value !== ''): ?><img src="<?php echo esc_url($field_value); ?>" alt="" data-media-preview-image><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    };
?>
    
    <div class="gb-builder-header">
        <div>
            <a href="?tab=pages" class="gb-back-link" style="text-decoration:none; color:#2e7d32; font-weight:700; font-size:13px;">← Back to Pages</a>
            <h1 class="gb-title" style="margin:10px 0 0; font-size:28px; font-weight:800; color:#0f172a;">Editing: <?php echo esc_html($p_obj->post_title); ?></h1>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="<?php echo get_permalink($pid); ?>" target="_blank" class="gb-btn-small-os" style="height:44px;">Preview ↗</a>
            <button type="submit" form="page-builder-form" class="gb-header-btn" style="height:44px; background:#2e7d32; color:white; border:none; padding:0 30px; border-radius:8px; font-weight:700; cursor:pointer;">Save Content</button>
        </div>
    </div>

    <div class="gb-builder-layout">
        <aside class="gb-builder-nav">
            <div class="gb-nav-card">
                <label class="gb-label">Quick Jump</label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="#hero" class="gb-btn-small-os" style="text-align:left;">🦸 Hero</a>
                    <a href="#trust" class="gb-btn-small-os" style="text-align:left;">⭐ Trust Bar</a>
                    <a href="#modules" class="gb-btn-small-os" style="text-align:left;">🧩 Modules</a>
                    <a href="#cta" class="gb-btn-small-os" style="text-align:left;">📣 Bottom CTA</a>
                </div>
            </div>
        </aside>

        <div class="gb-builder-content">
            <form method="post" id="page-builder-form">
                <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
                <input type="hidden" name="gb_save_action" value="save_page">
                <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

                <div id="hero" class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-format-image"></span> Hero Header</div>
                    <div class="gb-editor-body">
                        <?php 
                            $h_bg_k = $is_front ? '_gb_hero_bg' : ($slug == 'how-we-do-it' ? '_gb_about_hero_bg' : ($slug == 'what-we-do' ? '_gb_svc_hero_bg' : ($slug == 'join' ? '_gb_join_hero_bg' : ($slug == 'cleaner-listings' ? '_gb_listings_hero_bg' : ''))));
                            $h_title_k = $is_front
                                ? '_gb_hero_headline'
                                : ($slug == 'how-it-works'
                                    ? '_gb_hiw_hero_title'
                                    : ($slug == 'how-we-do-it'
                                        ? '_gb_about_hero_title'
                                        : ($slug == 'what-we-do'
                                            ? '_gb_svc_hero_title'
                                            : ($slug == 'cleaner-listings' ? '_gb_listings_title'
                                                : ($slug == 'join' ? '_gb_join_hero_title' : '')))));
                            $h_sub_k = $is_front
                                ? '_gb_hero_sub'
                                : ($slug == 'how-it-works'
                                    ? '_gb_hiw_hero_sub'
                                    : ($slug == 'how-we-do-it'
                                        ? '_gb_about_hero_sub'
                                        : ($slug == 'what-we-do'
                                            ? '_gb_svc_hero_sub'
                                            : ($slug == 'cleaner-listings' ? '_gb_listings_sub'
                                                : ($slug == 'join' ? '_gb_join_hero_sub' : '')))));
                        ?>
                        <?php if ($h_bg_k): ?>
                            <?php $gb_render_media_field($h_bg_k, 'Background image', $gb_field_value($h_bg_k)); ?>
                            <?php if ($slug === 'what-we-do' || $slug === 'cleaner-listings'): ?>
                                <p style="margin:-10px 0 18px; color:#94a3b8; font-size:12px;">Leave this blank for the solid green header.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($h_title_k && $h_sub_k): ?>
                            <div class="gb-form-row"><label class="gb-label">Headline</label><input type="text" name="<?php echo esc_attr($h_title_k); ?>" class="gb-input" value="<?php echo esc_attr($gb_field_value($h_title_k)); ?>"></div>
                            <div class="gb-form-row"><label class="gb-label">Subtitle</label><textarea name="<?php echo esc_attr($h_sub_k); ?>" class="gb-textarea"><?php echo esc_textarea($gb_field_value($h_sub_k)); ?></textarea></div>
                            <?php if ($is_front): ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row">
                                        <label class="gb-label">Primary Hero Button Text</label>
                                        <input type="text" name="_gb_hero_btn_1" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hero_btn_1')); ?>">
                                    </div>
                                    <div class="gb-form-row">
                                        <label class="gb-label">Primary Hero Button Link</label>
                                        <input type="text" name="_gb_hero_link_1" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hero_link_1')); ?>">
                                    </div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row">
                                        <label class="gb-label">Secondary Hero Button Text</label>
                                        <input type="text" name="_gb_hero_btn_2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hero_btn_2')); ?>">
                                    </div>
                                    <div class="gb-form-row">
                                        <label class="gb-label">Secondary Hero Button Link</label>
                                        <input type="text" name="_gb_hero_link_2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hero_link_2')); ?>">
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="margin:0; color:#64748b; font-size:13px;">This page uses standard content editing only. Use the Body Text Content editor below.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="trust" class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-awards"></span> Trust & Social Proof</div>
                    <div class="gb-editor-body">
                        <div class="gb-form-row">
                            <label class="gb-label">Hero Trust Bar Content</label>
                            <input type="text" 
                                name="_gb_trust_banner" 
                                class="gb-input"
                                value="<?php echo esc_attr($gb_field_value('_gb_trust_banner')); ?>" 
                                placeholder="e.g. Trusted by 500+ Homes Across the UK • 100% Vetted Cleaners">
                        </div>
                    </div>
                </div>

                <div id="modules">
                    <?php if ($is_front): ?>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-megaphone"></span> Value Proposition & Trust</div>
                            <div class="gb-editor-body">
                                <?php $gb_render_media_field('_gb_split_img', 'Image', $gb_field_value('_gb_split_img')); ?>
                                <div class="gb-form-row"><label class="gb-label">Heading</label><input type="text" name="_gb_split_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_split_head')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Body Text</label><textarea name="_gb_split_body" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_split_body')); ?></textarea></div>
                                <div class="gb-form-row"><label class="gb-label">Bullet Items</label>
                                    <div class="gb-link-row"><?php for($i=1;$i<=4;$i++): ?><input type="text" name="_gb_split_list_<?php echo $i; ?>" class="gb-input" placeholder="Prop <?php echo $i; ?>" value="<?php echo esc_attr($gb_field_value("_gb_split_list_{$i}")); ?>"><?php endfor; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-search"></span> How It Works (Steps)</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Section Heading</label><input type="text" name="_gb_hiw_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hiw_head')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Section Subheading</label><textarea name="_gb_hiw_sub" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_hiw_sub')); ?></textarea></div>
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-sub-head">Step <?php echo $i; ?></div>
                                    <div class="gb-form-row"><input type="text" name="_gb_step<?php echo $i; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_step{$i}_title")); ?>"><textarea name="_gb_step<?php echo $i; ?>_text" class="gb-textarea" style="margin-top:8px;"><?php echo esc_textarea($gb_field_value("_gb_step{$i}_text")); ?></textarea></div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-star-filled"></span> Features & Reviews</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row">
                                    <label class="gb-label">Features Section Heading</label>
                                    <input type="text" name="_gb_feat_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_feat_head')); ?>">
                                </div>
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-sub-head">Feature <?php echo $i; ?></div>
                                    <div class="gb-form-row">
                                        <input type="text" name="_gb_feat<?php echo $i; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_feat{$i}_title")); ?>">
                                        <textarea name="_gb_feat<?php echo $i; ?>_text" class="gb-textarea" style="margin-top:8px;"><?php echo esc_textarea($gb_field_value("_gb_feat{$i}_text")); ?></textarea>
                                    </div>
                                <?php endfor; ?>
                                <div class="gb-form-row">
                                    <label class="gb-label">Reviews Section Heading</label>
                                    <input type="text" name="_gb_testi_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_testi_head')); ?>">
                                </div>
                                <?php for($i=1;$i<=2;$i++): ?>
                                    <div class="gb-sub-head">Review <?php echo $i; ?></div>
                                    <div class="gb-form-row">
                                        <textarea name="_gb_testi<?php echo $i; ?>_text" class="gb-textarea"><?php echo esc_textarea($gb_field_value("_gb_testi{$i}_text")); ?></textarea>
                                        <input type="text" name="_gb_testi<?php echo $i; ?>_auth" class="gb-input" style="margin-top:8px;" placeholder="Author" value="<?php echo esc_attr($gb_field_value("_gb_testi{$i}_auth")); ?>">
                                    </div>
                                <?php endfor; ?>
                                <?php $gb_render_media_field('_gb_mid_img', 'Middle showcase image', $gb_field_value('_gb_mid_img')); ?>
                            </div>
                        </div>

                    <?php elseif ($slug == 'how-it-works'): ?>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-admin-home"></span> Homeowner Pathway</div>
                            <div class="gb-editor-body">
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-sub-head">Step <?php echo $i; ?></div>
                                    <?php $gb_render_media_field("_gb_hiw_s{$i}_img", "Step {$i} image", $gb_field_value("_gb_hiw_s{$i}_img")); ?>
                                    <div class="gb-form-row"><input type="text" name="_gb_hiw_s<?php echo $i; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_hiw_s{$i}_title")); ?>"><textarea name="_gb_hiw_s<?php echo $i; ?>_desc" class="gb-textarea" style="margin-top:8px;"><?php echo esc_textarea($gb_field_value("_gb_hiw_s{$i}_desc")); ?></textarea></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-id-alt"></span> Cleaner Pathway & FAQs</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Section Header</label><input type="text" name="_gb_hiw_cl_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hiw_cl_head')); ?>"></div>
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-form-row"><label class="gb-label">Step <?php echo $i; ?></label><input type="text" name="_gb_hiw_cl_s<?php echo $i; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_hiw_cl_s{$i}_title")); ?>"><input type="text" name="_gb_hiw_cl_s<?php echo $i; ?>_desc" class="gb-input" style="margin-top:8px;" value="<?php echo esc_attr($gb_field_value("_gb_hiw_cl_s{$i}_desc")); ?>"></div>
                                <?php endfor; ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Cleaner CTA Button Text</label><input type="text" name="_gb_hiw_cl_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hiw_cl_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Cleaner CTA Button Link</label><input type="text" name="_gb_hiw_cl_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hiw_cl_link')); ?>"></div>
                                </div>
                                <?php for($i=1;$i<=4;$i++): ?>
                                    <div class="gb-sub-head">FAQ <?php echo $i; ?></div>
                                    <div class="gb-form-row"><input type="text" name="_gb_hiw_faq<?php echo $i; ?>_q" class="gb-input" placeholder="Question" value="<?php echo esc_attr($gb_field_value("_gb_hiw_faq{$i}_q")); ?>"><textarea name="_gb_hiw_faq<?php echo $i; ?>_a" class="gb-textarea" style="margin-top:8px;"><?php echo esc_textarea($gb_field_value("_gb_hiw_faq{$i}_a")); ?></textarea></div>
                                <?php endfor; ?>
                            </div>
                        </div>

                    <?php elseif ($slug == 'how-we-do-it'): ?>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-book-alt"></span> Story & Vetting</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Story Headline</label><input type="text" name="_gb_story_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_story_head')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Story Lead Paragraph</label><textarea name="_gb_story_lead" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_story_lead')); ?></textarea></div>
                                <div class="gb-form-row"><label class="gb-label">Story Body</label><textarea name="_gb_story_body" class="gb-textarea" style="height:150px;"><?php echo esc_textarea($gb_field_value('_gb_story_body')); ?></textarea></div>
                                <?php $gb_render_media_field('_gb_story_img', 'Story image', $gb_field_value('_gb_story_img')); ?>

                                <div class="gb-sub-head">Stats Bar</div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Stat 1 Number</label><input type="text" name="_gb_stat1_num" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_stat1_num')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Stat 1 Label</label><input type="text" name="_gb_stat1_label" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_stat1_label')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Stat 2 Number</label><input type="text" name="_gb_stat2_num" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_stat2_num')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Stat 2 Label</label><input type="text" name="_gb_stat2_label" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_stat2_label')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Stat 3 Number</label><input type="text" name="_gb_stat3_num" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_stat3_num')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Stat 3 Label</label><input type="text" name="_gb_stat3_label" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_stat3_label')); ?>"></div>
                                </div>

                                <div class="gb-sub-head">Vetting Intro</div>
                                <div class="gb-form-row"><label class="gb-label">Vetting Section Heading</label><input type="text" name="_gb_vet_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_vet_head')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Vetting Section Subheading</label><textarea name="_gb_vet_sub" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_vet_sub')); ?></textarea></div>
                                <?php for($i=1;$i<=4;$i++): ?>
                                    <div class="gb-sub-head">Vetting Step <?php echo $i; ?></div>
                                    <div class="gb-form-row"><input type="text" name="_gb_vet_s<?php echo $i; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_vet_s{$i}_title")); ?>"><textarea name="_gb_vet_s<?php echo $i; ?>_desc" class="gb-textarea" style="margin-top:8px;"><?php echo esc_textarea($gb_field_value("_gb_vet_s{$i}_desc")); ?></textarea></div>
                                <?php endfor; ?>
                            </div>
                        </div>

                    <?php elseif ($slug == 'what-we-do'): ?>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-forms"></span> Service Packages & Mission</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Services Section Heading</label><input type="text" name="_gb_svc_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_svc_head')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Services Section Subheading</label><textarea name="_gb_svc_sub" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_svc_sub')); ?></textarea></div>
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-sub-head">Package <?php echo $i; ?></div>
                                    <div class="gb-form-row">
                                        <input type="text" name="_gb_svc_<?php echo $i; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_svc_{$i}_title")); ?>">
                                        <textarea name="_gb_svc_<?php echo $i; ?>_desc" class="gb-textarea" style="margin-top:8px;"><?php echo esc_textarea($gb_field_value("_gb_svc_{$i}_desc")); ?></textarea>
                                        <textarea name="_gb_svc_<?php echo $i; ?>_list" class="gb-textarea" style="margin-top:8px;" placeholder="Bullet list (line by line)"><?php echo esc_textarea($gb_field_value("_gb_svc_{$i}_list")); ?></textarea>
                                        <input type="text" name="_gb_svc_<?php echo $i; ?>_btn" class="gb-input" style="margin-top:8px;" placeholder="Button text" value="<?php echo esc_attr($gb_field_value("_gb_svc_{$i}_btn")); ?>">
                                        <input type="text" name="_gb_svc_<?php echo $i; ?>_link" class="gb-input" style="margin-top:8px;" placeholder="Button link" value="<?php echo esc_attr($gb_field_value("_gb_svc_{$i}_link")); ?>">
                                    </div>
                                <?php endfor; ?>
                                <div class="gb-sub-head">Our Mission</div>
                                <?php $gb_render_media_field('_gb_svc_mis_img', 'Mission image', $gb_field_value('_gb_svc_mis_img')); ?>
                                <div class="gb-form-row"><label class="gb-label">Mission Headline</label><input type="text" name="_gb_svc_mis_title" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_svc_mis_title')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Mission Subheading</label><textarea name="_gb_svc_mis_sub" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_svc_mis_sub')); ?></textarea></div>
                                <div class="gb-form-row"><label class="gb-label">Mission Body</label><textarea name="_gb_svc_mis_body" class="gb-textarea" style="height:120px;"><?php echo esc_textarea($gb_field_value('_gb_svc_mis_body')); ?></textarea></div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Mission Button Text</label><input type="text" name="_gb_svc_mis_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_svc_mis_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Mission Button Link</label><input type="text" name="_gb_svc_mis_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_svc_mis_link')); ?>"></div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($slug == 'join'): ?>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-buddicons-groups"></span> Hero Call-to-Action Buttons</div>
                            <div class="gb-editor-body">
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Primary Button Text</label><input type="text" name="_gb_join_hero_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_hero_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Primary Button Link</label><input type="text" name="_gb_join_hero_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_hero_link')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Secondary Button Text</label><input type="text" name="_gb_join_hero_btn2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_hero_btn2')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Secondary Button Link</label><input type="text" name="_gb_join_hero_link2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_hero_link2')); ?>"></div>
                                </div>
                            </div>
                        </div>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-heart"></span> Benefits ("Why Join")</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Section Heading</label><input type="text" name="_gb_join_ben_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_ben_head')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Section Subheading</label><textarea name="_gb_join_ben_sub" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_join_ben_sub')); ?></textarea></div>
                                <?php for($i=1;$i<=4;$i++): ?>
                                    <div class="gb-sub-head">Benefit <?php echo $i; ?></div>
                                    <div class="gb-form-row">
                                        <input type="text" name="_gb_join_b<?php echo $i; ?>_title" class="gb-input" placeholder="Title" value="<?php echo esc_attr($gb_field_value("_gb_join_b{$i}_title")); ?>">
                                        <textarea name="_gb_join_b<?php echo $i; ?>_text" class="gb-textarea" style="margin-top:8px;" placeholder="Description"><?php echo esc_textarea($gb_field_value("_gb_join_b{$i}_text")); ?></textarea>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-chart-bar"></span> Earnings / Stats Bar</div>
                            <div class="gb-editor-body">
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-link-row">
                                        <div class="gb-form-row"><label class="gb-label">Stat <?php echo $i; ?> Number</label><input type="text" name="_gb_join_stat<?php echo $i; ?>_num" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_join_stat{$i}_num")); ?>"></div>
                                        <div class="gb-form-row"><label class="gb-label">Stat <?php echo $i; ?> Label</label><input type="text" name="_gb_join_stat<?php echo $i; ?>_label" class="gb-input" value="<?php echo esc_attr($gb_field_value("_gb_join_stat{$i}_label")); ?>"></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-list-view"></span> How To Start (Steps)</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Section Heading</label><input type="text" name="_gb_join_steps_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_steps_head')); ?>"></div>
                                <?php for($i=1;$i<=3;$i++): ?>
                                    <div class="gb-sub-head">Step <?php echo $i; ?></div>
                                    <div class="gb-form-row">
                                        <input type="text" name="_gb_join_s<?php echo $i; ?>_title" class="gb-input" placeholder="Title" value="<?php echo esc_attr($gb_field_value("_gb_join_s{$i}_title")); ?>">
                                        <textarea name="_gb_join_s<?php echo $i; ?>_desc" class="gb-textarea" style="margin-top:8px;" placeholder="Description"><?php echo esc_textarea($gb_field_value("_gb_join_s{$i}_desc")); ?></textarea>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="gb-editor-section">
                            <div class="gb-editor-header"><span class="dashicons dashicons-format-quote"></span> Cleaner Testimonial</div>
                            <div class="gb-editor-body">
                                <div class="gb-form-row"><label class="gb-label">Quote</label><textarea name="_gb_join_testi_text" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_join_testi_text')); ?></textarea></div>
                                <div class="gb-form-row"><label class="gb-label">Attribution</label><input type="text" name="_gb_join_testi_auth" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_testi_auth')); ?>"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="cta" class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-megaphone"></span> Bottom CTA Banner</div>
                    <div class="gb-editor-body">
                        <?php 
                            $c_title_k = $is_front
                                ? '_gb_cta_heading'
                                : ($slug == 'how-it-works'
                                    ? '_gb_hiw_cta_title'
                                    : ($slug == 'how-we-do-it'
                                        ? '_gb_about_cta_head'
                                        : ($slug == 'what-we-do' ? '_gb_svc_cta_title'
                                            : ($slug == 'join' ? '_gb_join_cta_title' : ''))));
                            $c_sub_k = $is_front
                                ? '_gb_cta_sub'
                                : ($slug == 'how-it-works'
                                    ? '_gb_hiw_cta_desc'
                                    : ($slug == 'how-we-do-it'
                                        ? '_gb_about_cta_sub'
                                        : ($slug == 'what-we-do' ? '_gb_svc_cta_sub'
                                            : ($slug == 'join' ? '_gb_join_cta_sub' : ''))));
                        ?>
                        <?php if ($c_title_k && $c_sub_k): ?>
                            <div class="gb-form-row"><label class="gb-label">CTA Headline</label><input type="text" name="<?php echo esc_attr($c_title_k); ?>" class="gb-input" value="<?php echo esc_attr($gb_field_value($c_title_k)); ?>"></div>
                            <div class="gb-form-row"><label class="gb-label">CTA Description</label><textarea name="<?php echo esc_attr($c_sub_k); ?>" class="gb-textarea"><?php echo esc_textarea($gb_field_value($c_sub_k)); ?></textarea></div>
                            <?php if ($is_front): ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Primary CTA Button Text</label><input type="text" name="_gb_cta_btn_1" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_cta_btn_1')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Primary CTA Button Link</label><input type="text" name="_gb_cta_link_1" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_cta_link_1')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Secondary CTA Button Text</label><input type="text" name="_gb_cta_btn_2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_cta_btn_2')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Secondary CTA Button Link</label><input type="text" name="_gb_cta_link_2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_cta_link_2')); ?>"></div>
                                </div>
                            <?php elseif ($slug === 'how-it-works'): ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">CTA Button Text</label><input type="text" name="_gb_hiw_cta_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hiw_cta_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">CTA Button Link</label><input type="text" name="_gb_hiw_cta_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_hiw_cta_link')); ?>"></div>
                                </div>
                            <?php elseif ($slug === 'how-we-do-it'): ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Primary CTA Button Text</label><input type="text" name="_gb_about_btn1_text" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_about_btn1_text')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Primary CTA Button Link</label><input type="text" name="_gb_about_btn1_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_about_btn1_link')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Secondary CTA Button Text</label><input type="text" name="_gb_about_btn2_text" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_about_btn2_text')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Secondary CTA Button Link</label><input type="text" name="_gb_about_btn2_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_about_btn2_link')); ?>"></div>
                                </div>
                            <?php elseif ($slug === 'what-we-do'): ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">CTA Button Text</label><input type="text" name="_gb_svc_cta_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_svc_cta_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">CTA Button Link</label><input type="text" name="_gb_svc_cta_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_svc_cta_link')); ?>"></div>
                                </div>
                            <?php elseif ($slug === 'join'): ?>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">CTA Button Text</label><input type="text" name="_gb_join_cta_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_cta_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">CTA Button Link</label><input type="text" name="_gb_join_cta_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_join_cta_link')); ?>"></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="margin:0; color:#64748b; font-size:13px;">No structured bottom CTA fields are mapped for this page.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($slug === 'eco-products'): ?>
                <div class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-yes-alt"></span> Eco Products Page</div>
                    <div class="gb-editor-body">
                        <p style="margin:0 0 18px; color:#64748b; font-size:13px;">The heading uses the page title above. This controls the intro line below it.</p>
                        <div class="gb-form-row" style="margin-bottom:0;"><label class="gb-label">Intro line (under the heading)</label><textarea name="_gb_eco_hero_sub" class="gb-textarea"><?php echo esc_textarea($gb_field_value('_gb_eco_hero_sub')); ?></textarea></div>
                        <p style="margin:18px 0 0; padding:12px 14px; background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; color:#166534; font-size:13px;">
                            The product cards on this page are managed in
                            <a href="?tab=eco-products" style="color:#166534; font-weight:700;">Eco Products</a>
                            &mdash; add, edit and upload as many guides as you need there.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                /*
                 * Contact, Contact Support and the booking confirmation were the
                 * last pages whose wording lived in the template. Every string on
                 * them is now a field here.
                 */
                $gb_wording_fields = [];
                if ($slug === 'contact') {
                    $gb_wording_fields = [
                        ['_gb_contact_title', 'Heading', 'input'],
                        ['_gb_contact_sub', 'Line under the heading', 'input'],
                        ['_gb_contact_phone_label', 'Label before the phone number', 'input'],
                        ['_gb_contact_hours', 'Opening hours', 'input'],
                        ['_gb_contact_label_name', 'Form label: name', 'input'],
                        ['_gb_contact_label_email', 'Form label: email', 'input'],
                        ['_gb_contact_label_subject', 'Form label: subject', 'input'],
                        ['_gb_contact_ph_subject', 'Subject box placeholder', 'input'],
                        ['_gb_contact_label_message', 'Form label: message', 'input'],
                        ['_gb_contact_btn', 'Submit button', 'input'],
                        ['_gb_contact_success', 'Message after sending', 'textarea'],
                        ['_gb_contact_error', 'Message when the form is incomplete', 'textarea'],
                    ];
                } elseif ($slug === 'contact-support') {
                    $gb_wording_fields = [
                        ['_gb_csup_back', 'Back link', 'input'],
                        ['_gb_csup_title', 'Heading', 'input'],
                        ['_gb_csup_sub', 'Line under the heading', 'input'],
                        ['_gb_csup_phone_label', 'Label before the phone number', 'input'],
                        ['_gb_csup_hours', 'Opening hours', 'input'],
                        ['_gb_csup_btn', 'Submit button', 'input'],
                        ['_gb_csup_success_title', 'After sending: heading', 'input'],
                        ['_gb_csup_success_body', 'After sending: message', 'textarea'],
                        ['_gb_csup_error_title', 'On error: heading', 'input'],
                        ['_gb_csup_error_body', 'On error: message', 'textarea'],
                    ];
                } elseif ($slug === 'book-now') {
                    $gb_wording_fields = [
                        ['_gb_bk_title', 'Heading', 'input'],
                        ['_gb_bk_label_service', 'Form label: service type', 'input'],
                        ['_gb_bk_label_duration', 'Form label: duration', 'input'],
                        ['_gb_bk_label_date', 'Form label: date', 'input'],
                        ['_gb_bk_label_time', 'Form label: start time', 'input'],
                        ['_gb_bk_time_help', 'Hint under the time picker', 'input'],
                        ['_gb_bk_label_name', 'Form label: full name', 'input'],
                        ['_gb_bk_label_phone', 'Form label: phone', 'input'],
                        ['_gb_bk_label_email', 'Form label: email', 'input'],
                        ['_gb_bk_label_address', 'Form label: property address', 'input'],
                        ['_gb_bk_submit', 'Payment button', 'input'],
                        ['_gb_bk_note', 'Reassurance under the button', 'textarea'],
                    ];
                } elseif ($slug === 'booking-success') {
                    $gb_wording_fields = [
                        ['_gb_bs_paid_title', 'Heading once payment has gone through', 'input'],
                        ['_gb_bs_pending_title', 'Heading while payment is pending', 'input'],
                        ['_gb_bs_thanks', 'Thank you line — {name} is replaced with the customer\'s name', 'input'],
                        ['_gb_bs_account_sub', 'Text above the create-account button', 'textarea'],
                        ['_gb_bs_account_btn', 'Create-account button', 'input'],
                        ['_gb_bs_not_found', 'Heading when no booking is found', 'input'],
                    ];
                }
                ?>
                <?php if (!empty($gb_wording_fields)): ?>
                <div class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-edit"></span> Page Wording</div>
                    <div class="gb-editor-body">
                        <p style="margin:0 0 18px; color:#64748b; font-size:13px;">Every word visitors see on this page. Leave a box empty to use the standard wording.</p>
                        <?php foreach ($gb_wording_fields as $i => $gb_wf): list($gb_key, $gb_label, $gb_type) = $gb_wf; ?>
                            <div class="gb-form-row"<?php echo $i === count($gb_wording_fields) - 1 ? ' style="margin-bottom:0;"' : ''; ?>>
                                <label class="gb-label"><?php echo esc_html($gb_label); ?></label>
                                <?php if ($gb_type === 'textarea'): ?>
                                    <textarea name="<?php echo esc_attr($gb_key); ?>" class="gb-textarea"><?php echo esc_textarea($gb_field_value($gb_key)); ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?php echo esc_attr($gb_key); ?>" class="gb-input" value="<?php echo esc_attr($gb_field_value($gb_key)); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div id="content" class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-editor-paragraph"></span> Body Text Content</div>
                    <div class="gb-editor-body">
                        <?php if($slug == 'cleaner-listings'): ?>
                            <div class="gb-form-row"><label class="gb-label">Eyebrow label above the headline (optional)</label><input type="text" name="_gb_listings_eyebrow" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_eyebrow')); ?>" placeholder="e.g. Liverpool &amp; Merseyside"></div>
                            <div class="gb-form-row">
                                <label class="gb-label">Selling points in the header (one per line)</label>
                                <textarea name="_gb_listings_usps" class="gb-textarea" style="min-height:110px;" placeholder="Leave blank to use the standard three."><?php echo esc_textarea($gb_field_value('_gb_listings_usps')); ?></textarea>
                                <p style="margin-top:8px; color:#94a3b8; font-size:12px;">Shown as a ticked row under the subtitle. Three short lines works best.</p>
                            </div>
                            <div class="gb-link-row">
                                <div class="gb-form-row"><label class="gb-label">Main button text</label><input type="text" name="_gb_listings_btn_1" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_btn_1')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Main button link</label><input type="text" name="_gb_listings_link_1" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_link_1')); ?>" placeholder="#gb-dir-search-head jumps to the search"></div>
                            </div>
                            <div class="gb-link-row">
                                <div class="gb-form-row"><label class="gb-label">Second button text (blank to hide)</label><input type="text" name="_gb_listings_btn_2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_btn_2')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Second button link</label><input type="text" name="_gb_listings_link_2" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_link_2')); ?>"></div>
                            </div>
                            <div class="gb-form-row"><label class="gb-label">Heading above the search box</label><input type="text" name="_gb_listings_search_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_search_head')); ?>"></div>
                            <div style="margin:22px 0; padding-top:18px; border-top:1px solid #f1f5f9;">
                                <h4 style="margin:0 0 4px; font-size:13px; color:#0f172a;">Search box &amp; cleaner card labels</h4>
                                <p style="margin:0 0 16px; color:#64748b; font-size:12.5px;">The wording on the search form and on each cleaner card. The service dropdown fills itself from your Services list, so there is nothing to set here for that.</p>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Postcode box placeholder</label><input type="text" name="_gb_listings_ph_postcode" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_ph_postcode')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">&ldquo;Any service&rdquo; option</label><input type="text" name="_gb_listings_any_service" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_any_service')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Search button</label><input type="text" name="_gb_listings_search_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_search_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Verified badge on cards</label><input type="text" name="_gb_listings_badge" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_badge')); ?>"></div>
                                </div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Card button 1 (profile)</label><input type="text" name="_gb_listings_btn_profile" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_btn_profile')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Card button 2 (booking)</label><input type="text" name="_gb_listings_btn_book" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_btn_book')); ?>"></div>
                                </div>
                                <div class="gb-form-row" style="margin-bottom:0;">
                                    <label class="gb-label">Results line</label>
                                    <input type="text" name="_gb_listings_results" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_results')); ?>">
                                    <p style="margin-top:8px; color:#94a3b8; font-size:12px;">Use <code>{count}</code> for the number of cleaners found and <code>{term}</code> for what they searched.</p>
                                </div>
                            </div>

                            <div class="gb-form-row"><label class="gb-label">No Results Message</label><input type="text" name="_gb_listings_none" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_none')); ?>"></div>
                            <div style="margin:22px 0 0; padding-top:18px; border-top:1px solid #f1f5f9;">
                                <h4 style="margin:0 0 4px; font-size:13px; color:#0f172a;">Customer reviews strip</h4>
                                <p style="margin:0 0 16px; color:#64748b; font-size:12.5px;">
                                    Shows your three most recent genuine 4 and 5 star reviews, with the overall rating.
                                    It pulls straight from real customer reviews &mdash; there is nothing to write here and
                                    nothing is invented. The whole section stays hidden until you have reviews that qualify.
                                    Clear the heading to hide it permanently.
                                </p>
                                <div class="gb-form-row" style="margin-bottom:0;"><label class="gb-label">Section heading</label><input type="text" name="_gb_listings_reviews_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_reviews_head')); ?>"></div>
                            </div>

                            <div style="margin:22px 0 0; padding-top:18px; border-top:1px solid #f1f5f9;">
                                <h4 style="margin:0 0 4px; font-size:13px; color:#0f172a;">&ldquo;How it works&rdquo; steps</h4>
                                <p style="margin:0 0 16px; color:#64748b; font-size:12.5px;">Three steps shown below the results. Clear the heading to hide the whole section.</p>
                                <div class="gb-form-row"><label class="gb-label">Section heading</label><input type="text" name="_gb_listings_hiw_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_hiw_head')); ?>"></div>
                                <?php for ($gb_n = 1; $gb_n <= 3; $gb_n++): ?>
                                    <div class="gb-link-row">
                                        <div class="gb-form-row"><label class="gb-label">Step <?php echo $gb_n; ?> title</label><input type="text" name="_gb_listings_hiw_<?php echo $gb_n; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_hiw_' . $gb_n . '_title')); ?>"></div>
                                        <div class="gb-form-row"><label class="gb-label">Step <?php echo $gb_n; ?> text</label><input type="text" name="_gb_listings_hiw_<?php echo $gb_n; ?>_text" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_hiw_' . $gb_n . '_text')); ?>"></div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div style="margin:22px 0 0; padding-top:18px; border-top:1px solid #f1f5f9;">
                                <h4 style="margin:0 0 4px; font-size:13px; color:#0f172a;">&ldquo;Why book with us&rdquo; cards</h4>
                                <p style="margin:0 0 16px; color:#64748b; font-size:12.5px;">Four benefit cards. Clear a card&rsquo;s title to hide that card, or the heading to hide the section.</p>
                                <div class="gb-form-row"><label class="gb-label">Section heading</label><input type="text" name="_gb_listings_why_head" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_why_head')); ?>"></div>
                                <?php for ($gb_n = 1; $gb_n <= 4; $gb_n++): ?>
                                    <div class="gb-link-row">
                                        <div class="gb-form-row"><label class="gb-label">Card <?php echo $gb_n; ?> title</label><input type="text" name="_gb_listings_why_<?php echo $gb_n; ?>_title" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_why_' . $gb_n . '_title')); ?>"></div>
                                        <div class="gb-form-row"><label class="gb-label">Card <?php echo $gb_n; ?> text</label><input type="text" name="_gb_listings_why_<?php echo $gb_n; ?>_text" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_why_' . $gb_n . '_text')); ?>"></div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div style="margin:22px 0 0; padding-top:18px; border-top:1px solid #f1f5f9;">
                                <h4 style="margin:0 0 4px; font-size:13px; color:#0f172a;">Closing call to action</h4>
                                <p style="margin:0 0 16px; color:#64748b; font-size:12.5px;">The green banner at the bottom of the page. Clear the heading to hide it.</p>
                                <div class="gb-form-row"><label class="gb-label">Heading</label><input type="text" name="_gb_listings_cta_title" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_cta_title')); ?>"></div>
                                <div class="gb-form-row"><label class="gb-label">Supporting line</label><input type="text" name="_gb_listings_cta_sub" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_cta_sub')); ?>"></div>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Button text</label><input type="text" name="_gb_listings_cta_btn" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_cta_btn')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Button link</label><input type="text" name="_gb_listings_cta_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_cta_link')); ?>"></div>
                                </div>
                            </div>

                            <div style="margin:22px 0 0; padding-top:18px; border-top:1px solid #f1f5f9;">
                                <h4 style="margin:0 0 4px; font-size:13px; color:#0f172a;">&ldquo;What does Verified mean?&rdquo; pop-up</h4>
                                <p style="margin:0 0 16px; color:#64748b; font-size:12.5px;">The link shown under the search box, and the explanation it opens.</p>
                                <div class="gb-link-row">
                                    <div class="gb-form-row"><label class="gb-label">Link text</label><input type="text" name="_gb_listings_verified_link" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_verified_link')); ?>"></div>
                                    <div class="gb-form-row"><label class="gb-label">Pop-up heading</label><input type="text" name="_gb_listings_verified_title" class="gb-input" value="<?php echo esc_attr($gb_field_value('_gb_listings_verified_title')); ?>"></div>
                                </div>
                                <div class="gb-form-row">
                                    <label class="gb-label">Show the link on which &ldquo;Why book with us&rdquo; card?</label>
                                    <?php $gb_vcard_raw = $gb_field_value('_gb_listings_verified_card'); $gb_vcard = ($gb_vcard_raw === '' ? 1 : (int) $gb_vcard_raw); ?>
                                    <select name="_gb_listings_verified_card" class="gb-input" style="height:44px;">
                                        <option value="0" <?php selected($gb_vcard, 0); ?>>Do not show the link</option>
                                        <?php for ($gb_n = 1; $gb_n <= 4; $gb_n++): ?>
                                            <option value="<?php echo $gb_n; ?>" <?php selected($gb_vcard, $gb_n); ?>>Card <?php echo $gb_n; ?><?php $gb_ct = $gb_field_value('_gb_listings_why_' . $gb_n . '_title'); echo $gb_ct !== '' ? ' — ' . esc_html($gb_ct) : ''; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="gb-form-row" style="margin-bottom:0;">
                                    <label class="gb-label">Pop-up text</label>
                                    <textarea name="_gb_listings_verified_body" class="gb-textarea" style="min-height:190px;" placeholder="Leave blank to use the standard explanation of our vetting checks."><?php echo esc_textarea($gb_field_value('_gb_listings_verified_body')); ?></textarea>
                                    <p style="margin-top:8px; color:#94a3b8; font-size:12px;">Leave a blank line between paragraphs. Several lines in a row become a ticked list; end a line with a colon to make it the list&rsquo;s intro. Leave the whole box empty to use the standard wording describing our actual checks.</p>
                                </div>
                            </div>
                        <?php elseif ($is_structured_core_page): ?>
                            <p style="margin:0; color:#64748b; font-size:13px;">This page is fully controlled by the structured fields above.</p>
                        <?php else: ?>
                            <?php wp_editor($p_obj->post_content, 'gb_main_content', ['textarea_rows' => 15, 'textarea_name' => 'gb_main_content']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php /* Every page needs a search snippet, including the ones whose words
                         live in structured fields and so have no content to fall back on. */ ?>
                <div class="gb-editor-section">
                    <div class="gb-editor-header"><span class="dashicons dashicons-search"></span> Search Snippet</div>
                    <div class="gb-editor-body">
                        <div class="gb-form-row" style="margin-bottom:0;">
                            <label class="gb-label">Google description</label>
                            <textarea name="_gb_seo_meta_desc" class="gb-textarea" style="min-height:90px;" maxlength="160" placeholder="One or two sentences describing this page."><?php echo esc_textarea(get_post_meta($p_obj->ID, '_gb_seo_meta_desc', true)); ?></textarea>
                            <p style="margin-top:8px; color:#94a3b8; font-size:12px;">The grey text under the page title in Google results. Aim for 120&ndash;155 characters. Leave blank and Google writes its own.</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($){
        function gbUpdateMediaField($field, url) {
            var $preview = $field.find('[data-media-preview]');
            var cleanUrl = $.trim(url || '');

            $field.toggleClass('has-value', !!cleanUrl);
            $preview.removeClass('is-empty is-error').empty();
            if (!cleanUrl) {
                $field.find('.gb-media-input').attr('title', '');
                $preview.addClass('is-empty');
                return;
            }

            $field.find('.gb-media-input').attr('title', cleanUrl);

            $('<img>', { src: cleanUrl, alt: '' })
                .on('error', function() {
                    $preview.empty().removeClass('is-empty').addClass('is-error');
                })
                .appendTo($preview);
        }

        $('[data-media-field]').each(function(){
            var $field = $(this);
            gbUpdateMediaField($field, $field.find('.gb-media-input').val());
        });

        $('.gb-upload-btn').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $field = $btn.closest('[data-media-field]');
            var $input = $field.find('.gb-media-input');
            // A fresh frame per click avoids stale selection state between fields.
            var frame = wp.media({ title: 'Select or upload image', button: { text: 'Use this image' }, multiple: false });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $input.val(att.url).trigger('change');
            });
            frame.open();
        });

        $('.gb-media-clear').on('click', function(e){
            e.preventDefault();
            var $field = $(this).closest('[data-media-field]');
            $field.find('.gb-media-input').val('').trigger('change');
        });

        $('.gb-media-input').on('input change', function(){
            var $input = $(this);
            gbUpdateMediaField($input.closest('[data-media-field]'), $input.val());
        });
    });
    </script>

<?php else: ?>
    <?php
    $front_id = (int) get_option('page_on_front');
    $all_pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ]);

    $pages_by_slug = [];
    foreach ($all_pages as $page_obj) {
        $pages_by_slug[$page_obj->post_name] = $page_obj;
    }

    $core_slugs = ['home', 'how-it-works', 'how-we-do-it', 'what-we-do', 'cleaner-listings', 'join', 'eco-products'];
    $legal_slugs = ['privacy', 'terms', 'cookies', 'gdpr', 'subscribers-terms-of-association'];
    // Customer-facing pages whose wording is editable but which are not part of
    // the marketing core. Previously these sat in the collapsed "Other Pages"
    // bucket, so nobody could tell they were editable at all.
    $customer_slugs = ['contact', 'contact-support', 'book-now', 'booking-success'];

    $core_pages = [];
    if ($front_id) {
        $front_page = get_post($front_id);
        if ($front_page && $front_page->post_type === 'page') {
            $core_pages[$front_page->ID] = $front_page;
        }
    }

    foreach ($core_slugs as $slug_key) {
        if (isset($pages_by_slug[$slug_key])) {
            $core_pages[$pages_by_slug[$slug_key]->ID] = $pages_by_slug[$slug_key];
        }
    }

    $legal_pages = [];
    foreach ($legal_slugs as $slug_key) {
        if (isset($pages_by_slug[$slug_key])) {
            $legal_pages[$pages_by_slug[$slug_key]->ID] = $pages_by_slug[$slug_key];
        }
    }

    $customer_pages = [];
    foreach ($customer_slugs as $slug_key) {
        if (isset($pages_by_slug[$slug_key])) {
            $customer_pages[$pages_by_slug[$slug_key]->ID] = $pages_by_slug[$slug_key];
        }
    }

    ?>

    <div class="booking-controls">
        <div>
            <h1 class="gb-title" style="font-size:28px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;">Site Content Management</h1>
            <p style="margin:5px 0 0; color:#64748b; font-size:14px;">Edit manager-facing pages quickly with direct edit access.</p>
        </div>
    </div>

    <?php if ($invalid_edit_target): ?>
        <div style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; border-radius:10px; padding:12px 14px; margin-bottom:20px;">
            The selected page could not be loaded. Please choose a page from the list below.
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 28px;">
        <div style="margin:0 0 10px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Core System Pages</div>
        <div class="gb-card" style="padding:0; overflow:hidden; background: white; border: 1px solid #e2e8f0; border-radius: 12px;">
            <table class="gb-booking-table">
                <thead>
                    <tr><th style="width:40%;">Page Identity</th><th>Type</th><th style="text-align:right;">Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($core_pages as $p): 
                        $is_home = ($p->ID === $front_id) || $p->post_name === 'home';
                    ?>
                            <tr>
                                <td><strong style="font-size:15px; color:#0f172a;"><?php echo $p->post_title; ?></strong><div style="font-size:11px; color:#94a3b8; margin-top:4px;">/<?php echo $p->post_name; ?></div></td>
                                <td>
                                    <span style="background:<?php echo $is_home?'#dcfce7':'#f1f5f9'; ?>; color:<?php echo $is_home?'#166534':'#475569'; ?>; border-radius:20px; padding:6px 12px; font-size:10px; font-weight:800; text-transform:uppercase;">
                                        <?php echo $is_home ? '🏠 Home':'📄 Core'; ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="?tab=pages&action=edit&id=<?php echo $p->ID; ?>" class="gb-btn-small-os">Edit Content</a>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div style="margin:0 0 10px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Legal & Policy Documentation</div>
        <div class="gb-card" style="padding:0; overflow:hidden; background: white; border: 1px solid #e2e8f0; border-radius: 12px;">
            <table class="gb-booking-table">
                <thead>
                    <tr><th style="width:40%;">Policy Name</th><th>Status</th><th style="text-align:right;">Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($legal_pages as $p): ?>
                            <tr>
                                <td><strong style="font-size:15px; color:#0f172a;"><?php echo $p->post_title; ?></strong><div style="font-size:11px; color:#94a3b8; margin-top:4px;">/<?php echo $p->post_name; ?></div></td>
                                <td>
                                    <span style="background:#f1f5f9; color:#475569; border-radius:20px; padding:6px 12px; font-size:10px; font-weight:800; text-transform:uppercase;">
                                        ⚖️ Legal
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="?tab=pages&action=edit&id=<?php echo $p->ID; ?>" class="gb-btn-small-os">Edit Policy</a>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    // Any remaining published/draft pages that aren't Core or Legal — make them editable too.
    $shown_ids = array_merge(array_keys($core_pages), array_keys($legal_pages), array_keys($customer_pages));
    $other_pages = [];
    foreach ($all_pages as $page_obj) {
        if (!in_array($page_obj->ID, $shown_ids, true)) {
            $other_pages[$page_obj->ID] = $page_obj;
        }
    }
    ?>
    <?php if (!empty($customer_pages)): ?>
    <div style="margin:34px 0 0;">
        <div style="margin:0 0 10px; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Customer-Facing Pages</div>
        <p style="margin:0 0 12px; color:#64748b; font-size:12.5px;">Contact, booking and confirmation. Every word a customer reads on these is editable under &ldquo;Page Wording&rdquo; inside each one.</p>
        <div class="gb-card" style="padding:0; overflow:hidden; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
            <div class="gb-table-scroll">
                <table class="gb-booking-table">
                    <thead><tr><th style="width:40%;">Page</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($customer_pages as $p): ?>
                            <tr>
                                <td><strong style="font-size:15px; color:#0f172a;"><?php echo esc_html($p->post_title); ?></strong><div style="font-size:11px; color:#94a3b8; margin-top:4px;">/<?php echo esc_html($p->post_name); ?></div></td>
                                <td>
                                    <span style="background:#ecfdf3; color:#166534; border-radius:20px; padding:6px 12px; font-size:10px; font-weight:800; text-transform:uppercase;">
                                        &#9998; Wording editable
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="?tab=pages&amp;action=edit&amp;id=<?php echo (int) $p->ID; ?>" class="gb-btn-small-os">Edit Page</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($other_pages)): ?>
    <details class="gb-pages-accordion">
        <summary>
            <span>
                <span class="gb-pages-accordion-title">Account &amp; System Pages</span>
                <span class="gb-pages-accordion-meta"><span class="gb-pages-accordion-count"><?php echo count($other_pages); ?></span> &mdash; sign-in, account and dashboard screens</span>
            </span>
            <span class="dashicons dashicons-arrow-down-alt2 gb-pages-accordion-chevron" aria-hidden="true"></span>
        </summary>
        <div class="gb-pages-accordion-body">
            <table class="gb-booking-table">
                <thead>
                    <tr><th style="width:40%;">Page Identity</th><th>Status</th><th style="text-align:right;">Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($other_pages as $p): ?>
                            <tr>
                                <td><strong style="font-size:15px; color:#0f172a;"><?php echo $p->post_title; ?></strong><div style="font-size:11px; color:#94a3b8; margin-top:4px;">/<?php echo $p->post_name; ?></div></td>
                                <td>
                                    <span style="background:#f1f5f9; color:#475569; border-radius:20px; padding:6px 12px; font-size:10px; font-weight:800; text-transform:uppercase;">
                                        📄 <?php echo esc_html(ucfirst($p->post_status)); ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="?tab=pages&action=edit&id=<?php echo $p->ID; ?>" class="gb-btn-small-os">Edit Content</a>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php endif; ?>

<?php endif; ?>
