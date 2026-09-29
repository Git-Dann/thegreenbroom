<?php
/**
 * A plain page.
 *
 * Any page without a Template Name of its own lands here - The Green Broom
 * Standard among them. It used to print the title and the content into a bare
 * <main> with no width, no padding and nothing in the stylesheet targeting it,
 * so the text ran the full width of the screen, hard against the left edge.
 *
 * Blog posts were given readable measure and spacing; pages never were. Its
 * own class rather than styling .site-main, because three other templates use
 * that name for layouts that set their own width.
 */

get_header(); ?>

<main class="site-main gb-page-main">
  <?php
  if (have_posts()) {
    while (have_posts()) {
      the_post();
      the_title('<h1>','</h1>');
      the_content();
    }
  }
  ?>
</main>

<?php get_footer(); ?>
