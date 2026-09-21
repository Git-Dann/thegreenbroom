<?php
/**
 * Client-supplied copy for the two Eco Product guides.
 *
 * Kept in its own file rather than inline in functions.php because it is
 * content, not logic, and it is long. The wording is the client's own and is
 * reproduced verbatim - in particular the safety warnings in "Make Your Own"
 * must not be paraphrased, softened or trimmed.
 *
 * Only used to seed the guides once. After that the copy lives in the
 * database and is edited from Manager OS > Eco Products.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_eco_guide_seed_content() {
    $koh = <<<'HTML'
<p>Looking for a quick and easy way to get started? Koh eco-friendly cleaning products are a great place to begin.</p>

<h2>A Simpler, Smarter Way to Clean Your Home</h2>

<h3>Why Choose Koh?</h3>
<p>Koh is designed to make cleaning your home easier while reducing waste and the number of products you need. Its eco-friendly, plant-based cleaning solutions are effective on a wide range of surfaces and support a more sustainable lifestyle.</p>

<h2>Key Benefits of Koh Cleaning Products</h2>

<h3>Multi-Purpose Cleaning</h3>
<p>Koh&rsquo;s versatile cleaning formula can be used on many hard surfaces around your home, including:</p>
<ul>
<li>Kitchen worktops</li>
<li>Bathroom surfaces</li>
<li>Glass and mirrors</li>
<li>Tiles</li>
<li>Dining tables</li>
<li>General household surfaces</li>
</ul>
<p>Using one product for multiple cleaning tasks helps reduce clutter and simplifies your cleaning routine.</p>

<h3>Eco-Friendly Ingredients</h3>
<p>Koh products are formulated with biodegradable, plant-based ingredients and avoid many of the harsh chemicals found in conventional household cleaners.</p>
<p>Benefits include:</p>
<ul>
<li>Reduced environmental impact</li>
<li>A more sustainable cleaning routine</li>
<li>Effective everyday cleaning performance</li>
</ul>

<h3>Refillable System</h3>
<p>Instead of throwing away plastic spray bottles, Koh uses reusable bottles that can be topped up with concentrated refill sachets.</p>
<p>This helps:</p>
<ul>
<li>Reduce single-use plastic waste</li>
<li>Minimise unnecessary packaging</li>
<li>Support a more environmentally friendly home</li>
</ul>

<h3>Koh Starter Kits</h3>
<p>If you&rsquo;re new to Koh, the Starter Kit is the easiest way to begin.</p>
<p>A typical starter kit includes:</p>
<ul>
<li>Reusable spray bottle</li>
<li>Concentrated cleaning solution</li>
<li>Premium microfibre cloths</li>
</ul>
<p>Everything you need is included, making it simple to switch from traditional cleaning products.</p>

<h3>Premium Microfibre Cloths</h3>
<p>Koh&rsquo;s reusable microfibre cloths are designed to work alongside the cleaning solution to remove:</p>
<ul>
<li>Dirt</li>
<li>Dust</li>
<li>Grease</li>
<li>Fingerprints</li>
</ul>
<p>The cloths can be washed and reused many times, reducing the need for disposable cleaning wipes and paper towels.</p>

<h3>Save Space, Save Money</h3>
<p>Because one product replaces several specialist cleaners, you&rsquo;ll need fewer bottles in your cleaning cupboard.</p>
<p>Over time, refill concentrates and reusable bottles can offer excellent long-term value while helping reduce household waste.</p>

<h2>Why Our Clients Love Koh</h2>
<ul>
<li>Simple, all-in-one cleaning solution</li>
<li>Reusable bottles with refill concentrates</li>
<li>Plant-based, biodegradable ingredients</li>
<li>High-quality reusable microfibre cloths</li>
<li>Less plastic waste</li>
<li>Fewer cleaning products to buy and store</li>
<li>Suitable for most hard household surfaces</li>
<li>Supports a more sustainable lifestyle</li>
</ul>

<h2>Make the Switch</h2>
<p>Choosing Koh is an easy way to simplify your cleaning routine while making more environmentally responsible choices.</p>
<p>Whether you&rsquo;re looking to reduce plastic waste, minimise harsh chemicals or simply make cleaning quicker and easier, Koh provides an effective solution for modern UK homes.</p>
<p>For more information about Koh products or to find the right starter kit for your home, please don&rsquo;t hesitate to ask.</p>
HTML;

    $diy = <<<'HTML'
<p><strong>A simple beginner&rsquo;s guide.</strong></p>
<p>You don&rsquo;t need a cupboard full of specialist products to keep your home clean. With a few inexpensive, everyday ingredients, you can make effective cleaning solutions for many routine household jobs.</p>
<p>This guide uses just four basic ingredients:</p>
<ul>
<li>White vinegar</li>
<li>Bicarbonate of soda</li>
<li>Washing-up liquid</li>
<li>Water</li>
</ul>
<p>You&rsquo;ll also need a few reusable spray bottles, cloths and a small container or jar.</p>

<p><strong>Important safety note:</strong> Never mix vinegar with bleach or other chlorine-based cleaning products. Never mix different commercial cleaning products. Bicarbonate of soda and vinegar should be used separately rather than mixed together, as they largely neutralise each other.</p>

<h2>1. Everyday Multi-Purpose Cleaner</h2>
<p><strong>Suitable for:</strong> Kitchen worktops, tables, cupboards, tiles and other washable surfaces.</p>
<p><strong>You will need:</strong></p>
<ul>
<li>250ml water</li>
<li>250ml white vinegar</li>
<li>A few drops of washing-up liquid</li>
<li>A reusable spray bottle</li>
</ul>
<p><strong>How to make it:</strong></p>
<ol>
<li>Pour the water into the spray bottle.</li>
<li>Add the white vinegar.</li>
<li>Add a few drops of washing-up liquid.</li>
<li>Gently shake to mix.</li>
</ol>
<p><strong>How to use:</strong> Spray lightly onto the surface and wipe with a clean microfibre cloth. For delicate surfaces, test a small inconspicuous area first.</p>
<p>Do not use vinegar-based cleaner on: marble, limestone, natural stone, unsealed stone or other acid-sensitive surfaces.</p>

<h2>2. Simple Bathroom Cleaner</h2>
<p><strong>Suitable for:</strong> Sinks, taps, baths, showers and ceramic tiles.</p>
<p><strong>You will need:</strong></p>
<ul>
<li>250ml white vinegar</li>
<li>250ml warm water</li>
<li>A few drops of washing-up liquid</li>
<li>A spray bottle</li>
</ul>
<p><strong>How to make it:</strong> Simply add everything to the bottle and gently shake.</p>
<p><strong>How to use:</strong></p>
<ol>
<li>Spray onto the surface.</li>
<li>Leave for a few minutes.</li>
<li>Wipe with a cloth or sponge.</li>
<li>Rinse with clean water where appropriate.</li>
<li>Dry with a clean cloth.</li>
</ol>
<p>The vinegar helps with light limescale, while the washing-up liquid helps remove soap and grease.</p>
<p><strong>Tip:</strong> For heavier limescale, apply neat white vinegar to a cloth and place it over the affected area for a short period before rinsing.</p>

<h2>3. Bicarbonate of Soda Scrub</h2>
<p>Bicarbonate of soda makes a useful gentle abrasive for jobs where a liquid cleaner isn&rsquo;t enough.</p>
<p><strong>Suitable for:</strong> Sinks, cooker surfaces, baths and other robust washable surfaces.</p>
<p><strong>You will need:</strong></p>
<ul>
<li>Bicarbonate of soda</li>
<li>A little water</li>
</ul>
<p><strong>How to make it:</strong> Put a few tablespoons of bicarbonate of soda into a small bowl. Add water a little at a time, stirring until you have a thick paste.</p>
<p><strong>How to use:</strong></p>
<ol>
<li>Apply the paste to the dirty area.</li>
<li>Gently rub with a damp cloth or sponge.</li>
<li>Rinse thoroughly.</li>
<li>Wipe dry.</li>
</ol>
<p>Don&rsquo;t use excessive pressure, particularly on glossy or easily scratched surfaces.</p>

<h2>4. A Simple Floor Cleaner</h2>
<p>For most washable hard floors, you can keep things extremely simple.</p>
<p><strong>You will need:</strong></p>
<ul>
<li>A bucket of warm water</li>
<li>A small amount of washing-up liquid</li>
</ul>
<p>Add just a few drops of washing-up liquid to the water. Too much detergent can leave a sticky residue, so less is better.</p>
<p><strong>How to use:</strong> Dip your mop into the solution and wring it out well before cleaning the floor.</p>
<p>Always check the manufacturer&rsquo;s instructions for your flooring first. In particular, don&rsquo;t use large amounts of water or vinegar on untreated wood, laminate or other water-sensitive flooring.</p>

<h2>Your Basic Eco Cleaning Kit</h2>
<p>You don&rsquo;t need much to get started.</p>
<p><strong>Ingredients</strong></p>
<ul>
<li><strong>White vinegar</strong> &mdash; limescale and general cleaning</li>
<li><strong>Bicarbonate of soda</strong> &mdash; gentle scrubbing</li>
<li><strong>Washing-up liquid</strong> &mdash; grease and general cleaning</li>
<li><strong>Water</strong> &mdash; dilution</li>
</ul>
<p><strong>Equipment</strong></p>
<ul>
<li>2&ndash;3 reusable spray bottles</li>
<li>Microfibre cloths</li>
<li>Sponge</li>
<li>Small bowl or jar</li>
<li>Bucket</li>
<li>Mop</li>
</ul>
<p>That&rsquo;s enough to tackle a surprisingly large proportion of everyday household cleaning.</p>

<h2>A Few Important Rules</h2>
<p><strong>Don&rsquo;t mix vinegar and bleach.</strong> Never mix vinegar with bleach. The combination can produce dangerous chlorine gas.</p>
<p><strong>Don&rsquo;t use vinegar everywhere.</strong> Vinegar is acidic and can damage certain materials, particularly:</p>
<ul>
<li>Marble</li>
<li>Limestone</li>
<li>Natural stone</li>
<li>Some metals</li>
<li>Certain wood finishes</li>
</ul>
<p>When in doubt, check the manufacturer&rsquo;s cleaning instructions.</p>
<p><strong>Don&rsquo;t make huge quantities.</strong> Homemade cleaners containing water generally don&rsquo;t contain preservatives. Better to make small batches and replace them regularly rather than storing them for months.</p>
<p><strong>Label your bottles.</strong> Write the contents on each bottle and keep homemade cleaning products out of the reach of children and pets.</p>
<p><strong>Remember that &ldquo;natural&rdquo; doesn&rsquo;t automatically mean safe.</strong> Vinegar and bicarbonate of soda are common household ingredients, but still need to be used sensibly.</p>

<h2>The Beginner&rsquo;s Golden Rule</h2>
<p>Start simple. You don&rsquo;t need lemon juice, essential oils, complicated recipes or ten different ingredients.</p>
<p>With white vinegar, bicarbonate of soda, washing-up liquid and water, together with reusable cloths and bottles, you can create a small, inexpensive cleaning kit that covers many everyday jobs.</p>
<p>The biggest environmental benefit often comes not from finding an exotic &ldquo;green&rdquo; ingredient, but from using less packaging, buying fewer products and reusing what you already have.</p>
HTML;

    return array(
        'koh' => array(
            'title' => 'A Quick & Easy Way to Get Started',
            'desc'  => 'Koh eco-friendly cleaning products are a great place to begin: one plant-based formula for most hard surfaces, refillable bottles and reusable cloths.',
            'body'  => $koh,
        ),
        'diy' => array(
            'title' => 'Make Your Own',
            'desc'  => 'Make effective cleaning products at home from four everyday ingredients, with the safety rules that matter.',
            'body'  => $diy,
        ),
    );
}
