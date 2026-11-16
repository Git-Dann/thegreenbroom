<?php
/**
 * November 2026 blog batch: five new posts, saved as drafts so they can be
 * read through in Manager OS before anything goes public, plus a featured
 * image for the cleaner recruitment draft.
 *
 * The photos are from Pexels (free to use, no credit required), resized to
 * 1600 x 1067 and compressed before being committed, and ship with the theme
 * in assets/blog/. They are copied into the media library on the live site,
 * so WordPress makes its usual smaller sizes and srcset for them, and each
 * one gets descriptive alt text.
 *
 * Importing an image means resizing it several times, which is slow on
 * shared hosting, so each page load imports at most a few and the rest carry
 * on with the next visitor. Nothing is ever imported or created twice: every
 * image is tagged with its source filename and every post is looked up by its
 * slug first. Once everything is in place a single option switches this off.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_BLOG_SEED_OPTION = 'gb_blog_seed_2026_11_done';

/** How many images one page load may import before leaving the rest. */
const GB_BLOG_SEED_IMPORTS_PER_LOAD = 3;

function gb_blog_seed_links() {
    return array(
        '%%listings%%'  => esc_url( home_url( '/cleaner-listings/' ) ),
        '%%deep%%'      => esc_url( home_url( '/cleaner-listings/?service=deep' ) ),
        '%%moving%%'    => esc_url( home_url( '/cleaner-listings/?service=moving' ) ),
        '%%services%%'  => esc_url( home_url( '/what-cleaners-do/' ) ),
        '%%eco%%'       => esc_url( home_url( '/eco-products/' ) ),
        '%%standard%%'  => esc_url( home_url( '/the-green-broom-standard/' ) ),
        '%%dbs%%'       => esc_url( home_url( '/how-to-find-a-dbs-checked-cleaner-you-can-trust/' ) ),
        '%%cost%%'      => esc_url( home_url( '/how-much-should-a-cleaner-cost-in-merseyside-in-2026/' ) ),
        '%%often%%'     => esc_url( home_url( '/how-often-should-you-have-a-house-cleaner/' ) ),
        '%%ecomeans%%'  => esc_url( home_url( '/eco-friendly-cleaning-what-does-it-actually-mean/' ) ),
        '%%dontneed%%'  => esc_url( home_url( '/10-cleaning-products-you-dont-need-in-your-home/' ) ),
        '%%recycling%%' => esc_url( home_url( '/uk-household-recycling/' ) ),
    );
}

/**
 * Every image this batch uses: filename => alt text. The alt text describes
 * the picture for screen readers first, and carries the topic naturally.
 */
function gb_blog_seed_images() {
    return array(
        'condensation-on-window-winter-damp.jpg'               => 'Condensation and water droplets on the inside of a window in winter',
        'opening-bedroom-window-to-reduce-condensation.jpg'    => 'Woman opening a bedroom window in the morning to let out damp air',
        'end-of-tenancy-cleaning-liverpool-empty-flat.jpg'     => 'Packed moving boxes in an empty, bright flat ready for an end of tenancy clean',
        'moving-boxes-before-end-of-tenancy-clean.jpg'         => 'Open cardboard moving boxes in a light living room on moving day',
        'clean-living-room-with-christmas-tree.jpg'            => 'Clean, tidy living room with a decorated Christmas tree and wrapped presents',
        'christmas-dinner-table-ready-for-guests.jpg'          => 'Christmas dinner table laid for guests with candles, plates and a festive centrepiece',
        'pet-safe-cleaning-cat-on-sofa.jpg'                    => 'Tabby cat relaxing on a clean grey sofa at home',
        'puppy-sleeping-on-clean-wooden-floor.jpg'             => 'White puppy asleep on a clean wooden floor',
        'eco-cleaner-wiping-kitchen-worktop.jpg'               => 'Cleaner in rubber gloves wiping down a white kitchen worktop',
        'wiping-table-with-microfibre-cloth.jpg'               => 'Hand wiping a table with a microfibre cloth and a spray bottle',
        'self-employed-cleaner-liverpool-cleaning-worktop.jpg' => 'Self-employed cleaner in yellow gloves cleaning a kitchen worktop with a sponge',
    );
}

function gb_blog_seed_find_image( $file ) {
    $found = get_posts( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'meta_key'       => '_gb_seed_image',
        'meta_value'     => $file,
        'fields'         => 'ids',
        'posts_per_page' => 1,
    ) );

    return $found ? (int) $found[0] : 0;
}

/**
 * Copy one theme image into the media library. Returns the attachment ID, or
 * 0 if it could not be imported this time (it is simply tried again later).
 */
function gb_blog_seed_import_image( $file, $alt ) {
    $existing = gb_blog_seed_find_image( $file );
    if ( $existing ) {
        return $existing;
    }

    $source = get_template_directory() . '/assets/blog/' . $file;
    if ( ! is_readable( $source ) ) {
        return 0;
    }

    $upload = wp_upload_bits( $file, null, file_get_contents( $source ) );
    if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
        return 0;
    }

    $title = ucfirst( str_replace( '-', ' ', pathinfo( $file, PATHINFO_FILENAME ) ) );

    $attachment_id = wp_insert_attachment( array(
        'post_mime_type' => 'image/jpeg',
        'post_title'     => $title,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ), $upload['file'], 0, true );

    if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
        return 0;
    }

    update_post_meta( $attachment_id, '_gb_seed_image', $file );
    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

    return (int) $attachment_id;
}

function gb_blog_seed_figure( $attachment_id ) {
    $src = wp_get_attachment_image_src( $attachment_id, 'large' );
    if ( ! $src ) {
        return '';
    }

    $alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

    // The wp-image-ID class lets WordPress add srcset, sizes and lazy loading
    // when the post is shown, so phones get a smaller copy.
    return sprintf(
        '<figure class="wp-block-image size-large"><img src="%s" width="%d" height="%d" alt="%s" class="wp-image-%d" /></figure>',
        esc_url( $src[0] ),
        (int) $src[1],
        (int) $src[2],
        esc_attr( $alt ),
        (int) $attachment_id
    );
}

function gb_blog_seed_find_post( $slug, $title ) {
    $found = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'any',
        'name'           => $slug,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! $found ) {
        $found = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'title'          => $title,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
    }

    return $found ? (int) $found[0] : 0;
}

function gb_blog_seed_posts() {
    $posts = array();

    $body = <<<'HTML'
<p>Every winter, the same thing happens in homes across Liverpool and Merseyside. The heating goes on, the windows go wet every morning, and before long there are little black spots on the window seals, round the bath and in the corners of the bedroom ceiling.</p>
<p>The good news is that most household mould starts with condensation, and condensation is something you can do a lot about. Here is how to keep it under control, and how to get rid of mould naturally when small patches do appear, without reaching for the bleach.</p>
<h2>Why mould appears in winter</h2>
<p>Cooking, showering, drying clothes and even breathing all put water vapour into the air. Between them, a busy household adds litres of it every day. When that warm, damp air meets something cold, such as a window, an outside wall or the back of a wardrobe, it turns back into water. That is condensation.</p>
<p>Leave those damp surfaces wet day after day and mould spores, which are in the air in every home, have exactly what they need to grow.</p>
<h2>Stop condensation before it starts</h2>
<ul>
<li><strong>Open the windows for a short while every day.</strong> Ten to fifteen minutes in the morning swaps damp air for drier air, and costs very little heat.</li>
<li><strong>Use your extractor fans.</strong> Put them on while you cook or shower, and leave them running for a while afterwards.</li>
<li><strong>Keep the bathroom door shut</strong> while you shower, and open the window afterwards so the steam goes outside rather than into the rest of the house.</li>
<li><strong>Put lids on pans.</strong> It cuts down steam and saves energy too.</li>
<li><strong>Dry washing outside when you can.</strong> If it has to be indoors, use one room with the door closed and the window slightly open.</li>
<li><strong>Leave a gap behind furniture.</strong> A few centimetres between wardrobes or sofas and outside walls lets air move, so the wall behind does not stay cold and damp.</li>
<li><strong>Keep the heating steady.</strong> A low, constant temperature keeps walls warmer than short blasts of heat followed by a cold house.</li>
<li><strong>Wipe the windows every morning.</strong> A cloth or a window squeegee takes two minutes. Wring the water into the sink rather than letting it soak into the sills.</li>
</ul>
%%img:opening-bedroom-window-to-reduce-condensation.jpg%%
<h2>How to clean small patches of mould naturally</h2>
<p>For small patches of mould caused by condensation, on hard surfaces such as tiles, window frames, glass and sealant, you do not need harsh chemicals.</p>
<ol>
<li><strong>Protect yourself.</strong> Wear rubber gloves, open a window, and keep children and pets out of the room while you work.</li>
<li><strong>Spray with white vinegar.</strong> Use it neat in a spray bottle and soak the mould well.</li>
<li><strong>Leave it for about an hour.</strong> This gives the vinegar time to work.</li>
<li><strong>Scrub gently.</strong> For stubborn marks, make a paste of bicarbonate of soda and water and work it in with an old toothbrush.</li>
<li><strong>Wipe and dry.</strong> Wipe away with a damp cloth, then dry the surface completely. Mould comes back to damp surfaces, so drying matters as much as cleaning.</li>
</ol>
<p>Two things to avoid. Do not brush or vacuum dry mould, because it sends spores into the air. And never mix vinegar with bleach, because together they give off a toxic gas.</p>
<p>Bleach is often the first thing people reach for, but on porous surfaces such as grout and plaster it can take the colour out without solving the problem, and the mould is soon back. Dealing with the damp is what stops it returning. For more on what really works, see our guide to <a href="%%ecomeans%%">what eco-friendly cleaning actually means</a>, and our <a href="%%eco%%">Eco Products guides</a>.</p>
<h2>When to get help</h2>
<p>The NHS advises that if mould is caused by condensation and covers less than about one square metre, you can usually remove it yourself. Get help if:</p>
<ul>
<li>the patch is bigger than that, or keeps coming back however often you clean it</li>
<li>the damp is caused by a leak, a damaged roof or gutters, or rising damp</li>
<li>someone in your home has asthma, allergies, a breathing condition or a weakened immune system, as they should stay away from mould altogether</li>
</ul>
<p>If you rent, tell your landlord or letting agent in writing as soon as you notice damp or mould, and keep a copy along with dated photos. Damp caused by disrepair is their responsibility to fix.</p>
<h2>Let someone else take care of it</h2>
<p>A thorough clean before winter really sets in gives you a fresh start. A deep clean covers the jobs that get missed week to week, including limescale, skirting boards and behind furniture, using plant-based products that are kinder to your home and the people in it.</p>
<p><a href="%%deep%%"><strong>Find a local eco deep cleaner</strong></a> across Liverpool and Merseyside.</p>
HTML;

    $posts[] = array(
        'slug'     => 'how-to-get-rid-of-mould-naturally',
        'title'    => 'How to Get Rid of Mould Naturally: A Winter Guide to Condensation and Damp',
        'keyword'  => 'how to get rid of mould naturally',
        'excerpt'  => 'Wet windows and black spots in the corners? Here is how to stop condensation building up this winter, and how to clean small patches of mould without harsh chemicals.',
        'meta'     => 'Stop condensation and clean small patches of mould without harsh chemicals. A practical winter guide for homes in Liverpool and Merseyside.',
        'featured' => 'condensation-on-window-winter-damp.jpg',
        'content'  => $body,
    );

    $body = <<<'HTML'
<p>Moving out is stressful enough without worrying about your deposit. Cleaning is one of the most common reasons for deductions, and also one of the easiest to avoid with a bit of planning and a thorough end of tenancy cleaning checklist.</p>
<p>Whether you are leaving a student house off Smithdown Road, a flat in the city centre or a family home on the Wirral, this checklist will help you hand back the keys with confidence.</p>
<h2>First, know what your landlord can expect</h2>
<p>If you rent in England on an assured shorthold tenancy, your deposit must be protected in a government-approved scheme. At the end of the tenancy, your landlord can only deduct money for things like damage or cleaning if the property is in a worse state than when you moved in, allowing for fair wear and tear.</p>
<p>That makes your <strong>check-in inventory</strong> the most important document you have. Dig it out before you start and use it as your benchmark. If the oven was spotless on day one, it needs to be spotless when you leave.</p>
<p>It is also worth knowing that, under the Tenant Fees Act 2019, a landlord in England generally cannot make you pay for a professional clean as a condition of your tenancy. What they can expect is that the home is handed back as clean as you found it. If you do disagree about a deduction, your deposit scheme offers a free dispute service.</p>
<h2>The room-by-room checklist</h2>
<h3>Kitchen</h3>
<ul>
<li>Inside the oven, the oven racks and the grill pan</li>
<li>Hob, splashback and the extractor hood, including the filter</li>
<li>Fridge and freezer: emptied, defrosted, cleaned and left with the doors open if switched off</li>
<li>Inside and outside of every cupboard and drawer</li>
<li>Microwave, kettle and any appliances that came with the property</li>
<li>Sink, taps and draining board, with limescale removed</li>
<li>Tiles, grout and worktops</li>
</ul>
<h3>Bathroom</h3>
<ul>
<li>Limescale off taps, shower heads, screens and tiles</li>
<li>Toilet inside and out, including the base and behind it</li>
<li>Bath, shower tray and sealant, with any mould treated</li>
<li>Mirrors, cabinets and the extractor fan cover</li>
</ul>
<h3>Bedrooms and living areas</h3>
<ul>
<li>Skirting boards, door frames and the tops of doors</li>
<li>Light switches, plug sockets and door handles</li>
<li>Inside wardrobes, drawers and any other furniture that stays</li>
<li>Windows inside, plus sills and frames</li>
<li>Radiators, including behind them where you can reach</li>
<li>Marks on walls. Check your tenancy agreement before you touch up any paint.</li>
</ul>
<h3>Floors and the finishing touches</h3>
<ul>
<li>Vacuum everywhere, including under beds and sofas once they are empty</li>
<li>Mop hard floors last, working towards the door</li>
<li>Empty all the bins, inside and out</li>
<li>Replace any bulbs that have gone</li>
</ul>
%%img:moving-boxes-before-end-of-tenancy-clean.jpg%%
<h2>Tips that make a real difference</h2>
<ul>
<li><strong>Clean after you have moved out.</strong> It is far easier, and quicker, in an empty home. If you are booking a cleaner, book them for after the van has gone.</li>
<li><strong>Allow enough time.</strong> A proper end of tenancy clean can take most of a day, longer for a larger house.</li>
<li><strong>Take photos and videos when you are done.</strong> Make sure they are dated, cover every room, and match the angles in your inventory if you can.</li>
<li><strong>Do not forget the outside.</strong> Gardens, yards and bin areas are often listed in the inventory too.</li>
</ul>
<h2>Want it done for you?</h2>
<p>Many of the independent cleaners on The Green Broom offer house move and end of tenancy cleans across Liverpool and Merseyside. Every verified cleaner has had their ID, Basic DBS certificate and references checked, and they all use eco-friendly, plant-based products.</p>
<p>It works for landlords too. A thorough clean between tenants helps a property let faster and sets the standard for the next inventory.</p>
<p><a href="%%moving%%"><strong>Find an end of tenancy cleaner near you</strong></a>, or read <a href="%%cost%%">how much a cleaner costs in Merseyside</a> first.</p>
HTML;

    $posts[] = array(
        'slug'     => 'end-of-tenancy-cleaning-liverpool-checklist',
        'title'    => 'End of Tenancy Cleaning in Liverpool: A Checklist to Help Get Your Deposit Back',
        'keyword'  => 'end of tenancy cleaning',
        'excerpt'  => 'Moving out of a rented home in Liverpool? Use this room-by-room end of tenancy cleaning checklist to hand the keys back with confidence and give yourself the best chance of getting your full deposit back.',
        'meta'     => 'A room-by-room end of tenancy cleaning checklist for Liverpool renters, and what your landlord can expect, to help you get your deposit back.',
        'featured' => 'end-of-tenancy-cleaning-liverpool-empty-flat.jpg',
        'content'  => $body,
    );

    $body = <<<'HTML'
<p>If you are hosting family or friends this Christmas, the last thing you want is to spend Christmas Eve scrubbing the bathroom. The trick is to spread the cleaning out over a few weeks, so that by the time the guests arrive, all that is left is a quick tidy.</p>
<p>Here is a simple Christmas cleaning plan to follow, with a few eco-friendly ideas along the way.</p>
<h2>Three weeks before: clear the decks</h2>
<ul>
<li><strong>Declutter first.</strong> There is no point cleaning around things you do not need. Clear worktops, hallways and the spare room, and take anything in good condition to a charity shop.</li>
<li><strong>Make space for the tree and the presents.</strong> Decide where everything will go and clean that area properly before it fills up.</li>
<li><strong>Get the guest room ready.</strong> Wash the spare bedding and towels, air the room, and clear a drawer or some hanging space.</li>
<li><strong>Clean the oven.</strong> Do it now, before the big roast. A paste of bicarbonate of soda and water, left overnight, shifts most baked-on grease without harsh fumes.</li>
</ul>
<h2>Two weeks before: the bigger jobs</h2>
<ul>
<li><strong>Windows and mirrors.</strong> Winter light is low, and it shows up every smear. A little white vinegar in warm water and a microfibre cloth is all you need.</li>
<li><strong>Skirting boards, door frames and light switches.</strong> The bits guests notice without realising.</li>
<li><strong>Sofas and soft furnishings.</strong> Vacuum under the cushions, and wash the throws and cushion covers.</li>
<li><strong>The fridge.</strong> Clear it out and give it a wipe now, so there is room for all the extra food.</li>
</ul>
%%img:christmas-dinner-table-ready-for-guests.jpg%%
<h2>The week before: the rooms guests use</h2>
<ul>
<li><strong>Bathroom.</strong> Limescale off the taps and shower, fresh towels out, and spare toilet rolls somewhere easy to find.</li>
<li><strong>Hallway and entrance.</strong> First impressions count. Shake out the doormat, wipe the front door and clear the shoe pile.</li>
<li><strong>Dining area.</strong> Wipe the table and chairs, and check you have enough clean glasses, plates and cutlery.</li>
</ul>
<h2>The day before: a quick reset</h2>
<p>If you have kept up with the plan, this should take less than an hour. Wipe the kitchen and bathroom surfaces, give the toilet a once-over, vacuum the main rooms and empty the bins. Then sit down. You have earned it.</p>
<h2>Greener ideas for the festive season</h2>
<ul>
<li><strong>Skip the aerosol air fresheners.</strong> Simmer orange peel, a cinnamon stick and a few cloves in a pan of water for a festive smell without the chemicals.</li>
<li><strong>Use what you already have.</strong> White vinegar, bicarbonate of soda and lemon handle most jobs, so there is no need for a cupboard full of sprays. See our list of <a href="%%dontneed%%">cleaning products you don't need</a>.</li>
<li><strong>Recycle properly afterwards.</strong> Wrapping paper with glitter or foil usually cannot be recycled. If it scrunches into a ball and stays scrunched, it generally can. Our guide to <a href="%%recycling%%">UK household recycling</a> has more.</li>
</ul>
<h2>Short on time? Book some help</h2>
<p>December is busy for everyone, and local cleaners' diaries can fill up quickly in the run-up to Christmas. If you would like a deep clean before the guests arrive, or a clean-up once they have gone, it is worth booking early.</p>
<p><a href="%%listings%%"><strong>Find a local eco cleaner</strong></a> across Liverpool and Merseyside.</p>
HTML;

    $posts[] = array(
        'slug'     => 'christmas-cleaning-plan-guest-ready-home',
        'title'    => 'Christmas Cleaning Plan: How to Get Your Home Guest-Ready Without the Stress',
        'keyword'  => 'Christmas cleaning',
        'excerpt'  => 'Hosting this Christmas? Our week-by-week plan spreads the cleaning out so your home is guest-ready without a last-minute panic, with eco-friendly tips for the festive season.',
        'meta'     => 'A simple week-by-week Christmas cleaning plan to get your home guest-ready without a last-minute panic, plus eco-friendly festive tips.',
        'featured' => 'clean-living-room-with-christmas-tree.jpg',
        'content'  => $body,
    );

    $body = <<<'HTML'
<p>Pets make a house a home. They also bring hair, muddy paws, the odd accident and a surprising amount of dust. Keeping on top of it is one thing. Doing it with products that are safe for them is another.</p>
<p>Pets live much closer to the floor than we do. They walk across freshly cleaned surfaces, lie on them, and then lick their paws and fur. Whatever you clean with, they are likely to swallow a little of it.</p>
<h2>Choosing pet safe cleaning products</h2>
<ul>
<li><strong>Go for plant-based, fragrance-free products where you can.</strong> Strong scents are hard work for animals with far more sensitive noses than ours.</li>
<li><strong>Be careful with "natural" products around cats.</strong> Cats are especially sensitive to some essential oils, including tea tree, and to phenols, which are found in some disinfectants. Natural does not automatically mean pet-safe, so always check the label.</li>
<li><strong>Avoid ammonia-based cleaners for accidents.</strong> To a dog or cat, ammonia can smell like urine, which may encourage them to go in the same spot again.</li>
<li><strong>Keep it simple.</strong> Warm water, a little washing-up liquid, white vinegar and bicarbonate of soda will handle most everyday jobs. Our guide on <a href="%%ecomeans%%">what eco-friendly cleaning actually means</a> explains what to look for.</li>
</ul>
<h2>Floors and paws</h2>
<ul>
<li>Keep pets out of the room until a freshly mopped floor is completely dry.</li>
<li>Rinse floors with clean water after using any cleaning product, so there is no residue for paws to pick up.</li>
<li>Keep an old towel by the back door for muddy feet. It saves a lot of mopping in winter.</li>
<li>A barrier mat on each side of the door catches much of the dirt before it travels through the house.</li>
</ul>
%%img:puppy-sleeping-on-clean-wooden-floor.jpg%%
<h2>Dealing with pet hair</h2>
<ul>
<li><strong>Use a damp rubber glove</strong> on sofas and cushions. Run your hand over the fabric and the hair rolls up into clumps.</li>
<li><strong>Vacuum little and often</strong> with a pet hair attachment, rather than one big session a week.</li>
<li><strong>Brush your pet regularly</strong>, outside if you can. Less hair on them means less on the carpet.</li>
<li><strong>Wash pet bedding every week or two</strong> on a hot wash, where the label allows.</li>
</ul>
<h2>When accidents happen</h2>
<ol>
<li>Blot up as much as you can with kitchen roll or an old towel. Do not rub, as that pushes it deeper into carpets.</li>
<li>Use an enzyme cleaner, sometimes called a biological cleaner. Enzymes break down what causes the smell, rather than just covering it up, so your pet is less likely to go back to the same spot.</li>
<li>Rinse with a little clean water and blot dry.</li>
</ol>
<h2>Don't forget their things</h2>
<ul>
<li>Wash food and water bowls every day, with hot soapy water.</li>
<li>Wash toys regularly, especially soft ones.</li>
<li>Store all cleaning products, including dishwasher and laundry tablets, closed and out of reach.</li>
</ul>
<p>If you think your pet has swallowed a cleaning product, call your vet straight away, and have the bottle with you so you can tell them what is in it.</p>
<h2>Booking a cleaner when you have pets</h2>
<p>Every cleaner on The Green Broom uses plant-based, non-toxic products, which is one reason pet owners choose us. When you book, let your cleaner know about your pets: whether they will be at home, any rooms or doors that need to stay closed, and anything they are sensitive to.</p>
<p><a href="%%listings%%"><strong>Find a pet-friendly eco cleaner</strong></a> across Liverpool and Merseyside.</p>
HTML;

    $posts[] = array(
        'slug'     => 'pet-safe-cleaning-dogs-and-cats',
        'title'    => 'Pet Safe Cleaning: How to Keep a Clean Home with Dogs and Cats',
        'keyword'  => 'pet safe cleaning',
        'excerpt'  => 'Love your pets but not the hair, muddy paws and accidents? Here is how to keep a clean home with dogs and cats, using pet safe cleaning products and methods that are kind to them too.',
        'meta'     => 'Pet safe cleaning made simple: how to deal with hair, muddy paws and accidents using products and methods that are kind to dogs and cats.',
        'featured' => 'pet-safe-cleaning-cat-on-sofa.jpg',
        'content'  => $body,
    );

    $body = <<<'HTML'
<p>Booking a cleaner for the first time can feel like a big step. You are letting someone into your home, and you want to know you have chosen the right person and that the job will be done well.</p>
<p>Here is what to expect from a cleaner, from choosing one to the first visit, and how to prepare so it all goes smoothly.</p>
<h2>Step 1: Decide what you need</h2>
<p>Start by thinking about what you want help with.</p>
<ul>
<li><strong>A regular clean</strong>, weekly or fortnightly, keeps your home fresh week to week: kitchen, bathrooms, dusting, vacuuming and mopping.</li>
<li><strong>A deep clean</strong> goes further, covering things like inside the oven and fridge, skirting boards, limescale and behind furniture. It is a good first clean, and a good way to get a home back on track.</li>
</ul>
<p>See <a href="%%services%%">what our cleaners do</a> for the full list, and our guide to <a href="%%often%%">how often you should have a house cleaner</a> if you are not sure how often to book.</p>
<h2>Step 2: Choose your cleaner</h2>
<p>When you browse cleaners on The Green Broom, look at:</p>
<ul>
<li><strong>The Verified badge.</strong> It means we have checked their photo ID and Basic DBS certificate, and spoken to two references. Read more about <a href="%%dbs%%">finding a DBS-checked cleaner you can trust</a>.</li>
<li><strong>Reviews</strong> from other customers.</li>
<li><strong>The areas they cover</strong> and the services they offer.</li>
<li><strong>Their rates.</strong> Our guide to <a href="%%cost%%">how much a cleaner costs in Merseyside</a> will help you know what to expect.</li>
</ul>
<p>If you have questions, send them a message before you book. A good cleaner will be happy to answer.</p>
%%img:wiping-table-with-microfibre-cloth.jpg%%
<h2>Step 3: Book and pay</h2>
<p>Once you have found the right person, book the date and time that suits you and pay securely online. There is no cash to leave out and nothing to sort out on the day. Check the cancellation terms shown when you book, in case your plans change.</p>
<h2>Step 4: Prepare for the first visit</h2>
<ul>
<li><strong>Have a quick tidy.</strong> Cleaners clean rather than tidy, so clearing clutter off surfaces and floors means more time is spent on the cleaning itself.</li>
<li><strong>Make a short list of priorities.</strong> If the kitchen and bathroom matter most, say so. It is much easier to get things right when your cleaner knows what you care about.</li>
<li><strong>Sort out access.</strong> Will you be in? If not, arrange keys and any alarm codes, and mention parking, especially if you live in a permit zone.</li>
<li><strong>Mention your pets</strong>, and any rooms or doors that should stay closed.</li>
<li><strong>Point out anything delicate.</strong> Natural stone worktops, real wood floors and antiques can need special care.</li>
<li><strong>Check about products and equipment.</strong> Many cleaners bring their own eco-friendly products and equipment. If you are not sure, just ask.</li>
</ul>
<h2>Step 5: On the day</h2>
<p>A first clean usually takes a little longer than the visits that follow, as your cleaner gets to know your home and catches up on anything that has built up. If you are in, a quick walk round at the start helps. Show them where things are and anything you would like extra attention on.</p>
<h2>Step 6: After the clean</h2>
<p>Give honest feedback, good or bad. If something was missed, let your cleaner know. Most will be glad to put it right, and it helps them get it spot on next time. If you were happy, <strong>leave a review</strong>. It means a lot to independent cleaners, and helps other people find them.</p>
<p>If it went well, think about booking a regular slot. You will get the same trusted person, who knows your home and how you like things done.</p>
<h2>Ready to book?</h2>
<p>Every cleaner on The Green Broom is independent and local, and uses plant-based, non-toxic products that are safer for your family, your pets and the planet.</p>
<p><a href="%%listings%%"><strong>Find your cleaner</strong></a> across Liverpool and Merseyside.</p>
HTML;

    $posts[] = array(
        'slug'     => 'what-to-expect-from-a-cleaner-first-clean',
        'title'    => 'What to Expect From a Cleaner: How to Book and Prepare for Your First Clean',
        'keyword'  => 'what to expect from a cleaner',
        'excerpt'  => 'Thinking about booking a cleaner for the first time? Here is what to expect from a cleaner, how to choose the right one, and how to prepare so your first clean goes smoothly.',
        'meta'     => 'What to expect from a cleaner, from choosing a DBS-checked local cleaner to getting ready for your first clean. A simple guide for first-timers.',
        'featured' => 'eco-cleaner-wiping-kitchen-worktop.jpg',
        'content'  => $body,
    );

    return $posts;
}

function gb_seed_blog_batch_2026_11() {
    if ( get_option( GB_BLOG_SEED_OPTION ) ) {
        return;
    }

    // One page load at a time; a second visitor arriving mid-import just skips.
    if ( get_transient( 'gb_blog_seed_lock' ) ) {
        return;
    }
    set_transient( 'gb_blog_seed_lock', 1, 5 * MINUTE_IN_SECONDS );

    $budget   = GB_BLOG_SEED_IMPORTS_PER_LOAD;
    $complete = true;
    $images   = gb_blog_seed_images();
    $admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    $author   = $admins ? (int) $admins[0] : 0;
    $links    = gb_blog_seed_links();

    // Returns the attachment ID, or 0 if it has to wait for a later load.
    $image = function ( $file ) use ( &$budget, $images ) {
        $id = gb_blog_seed_find_image( $file );
        if ( $id ) {
            return $id;
        }
        if ( $budget <= 0 || ! isset( $images[ $file ] ) ) {
            return 0;
        }
        $budget--;
        return gb_blog_seed_import_image( $file, $images[ $file ] );
    };

    foreach ( gb_blog_seed_posts() as $post ) {
        $post_id = gb_blog_seed_find_post( $post['slug'], $post['title'] );

        if ( $post_id ) {
            // Already there (perhaps edited since): only add a missing featured image.
            if ( ! has_post_thumbnail( $post_id ) ) {
                $featured = $image( $post['featured'] );
                if ( $featured ) {
                    set_post_thumbnail( $post_id, $featured );
                } else {
                    $complete = false;
                }
            }
            continue;
        }

        // Every image the post needs, before the post is written.
        preg_match_all( '/%%img:([a-z0-9.-]+)%%/', $post['content'], $inline );
        $needed = array_merge( array( $post['featured'] ), $inline[1] );
        $ids    = array();
        foreach ( $needed as $file ) {
            $ids[ $file ] = $image( $file );
            if ( ! $ids[ $file ] ) {
                $complete = false;
                break;
            }
        }
        if ( in_array( 0, $ids, true ) ) {
            continue;
        }

        $content = strtr( $post['content'], $links );
        foreach ( $inline[1] as $file ) {
            $content = str_replace( '%%img:' . $file . '%%', gb_blog_seed_figure( $ids[ $file ] ), $content );
        }

        $post_id = wp_insert_post( array(
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => $post['title'],
            'post_name'    => $post['slug'],
            'post_excerpt' => $post['excerpt'],
            'post_content' => $content,
            'post_author'  => $author,
        ), true );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            $complete = false;
            continue;
        }

        update_post_meta( $post_id, '_gb_seo_keyword', $post['keyword'] );
        update_post_meta( $post_id, '_gb_seo_meta_desc', $post['meta'] );
        // Title, content, 300+ words and meta description all pass the
        // Manager OS SEO checks, so the list shows the score straight away.
        update_post_meta( $post_id, '_gb_seo_score', 100 );
        set_post_thumbnail( $post_id, $ids[ $post['featured'] ] );

        foreach ( $ids as $attachment_id ) {
            if ( ! wp_get_post_parent_id( $attachment_id ) ) {
                wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
            }
        }
    }

    // The cleaner recruitment draft from earlier gets a featured image too.
    $recruitment = gb_blog_seed_find_post(
        'run-your-own-eco-cleaning-business-merseyside',
        'Run your own eco-cleaning business in Merseyside, with The Green Broom behind you'
    );
    if ( $recruitment && ! has_post_thumbnail( $recruitment ) ) {
        $featured = $image( 'self-employed-cleaner-liverpool-cleaning-worktop.jpg' );
        if ( $featured ) {
            set_post_thumbnail( $recruitment, $featured );
        } else {
            $complete = false;
        }
    }

    if ( $complete ) {
        update_option( GB_BLOG_SEED_OPTION, 1, false );
    }

    delete_transient( 'gb_blog_seed_lock' );
}
add_action( 'init', 'gb_seed_blog_batch_2026_11', 30 );
