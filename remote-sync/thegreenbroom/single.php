<?php get_header(); ?>

<main class="site-main">
  <?php
  if (have_posts()) {
    while (have_posts()) {
      the_post();
      the_title('<h1>','</h1>');
      the_content();
      // You can add more post meta, comments etc here if needed
    }
  }
  ?>
</main>

<?php get_footer(); ?>