<?php
/**
 * The main template file
 * This is the most generic template file in a WordPress theme.
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">

        <?php
        if ( have_posts() ) :

            while ( have_posts() ) :
                the_post();
                ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <header class="entry-header">
                        <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                    </header>

                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </article>

                <?php
            endwhile;

        else :

            // Fallback content if no posts exist
            ?>
            <section class="no-results not-found">
                <header class="page-header">
                    <h1 class="page-title">Nothing Found</h1>
                </header>
                <div class="page-content">
                    <p>It seems we can&rsquo;t find what you&rsquo;re looking for.</p>
                </div>
            </section>
            <?php

        endif;
        ?>

    </div></main><?php
get_footer();