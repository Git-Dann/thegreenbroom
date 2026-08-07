<?php get_header(); ?>

<main class="site-main gb-article-main">
  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <article class="gb-article">
      <header class="gb-article-header">
        <div class="gb-article-kicker">The Green Broom Journal</div>
        <h1><?php the_title(); ?></h1>
        <div class="gb-article-meta"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></div>
      </header>

      <div class="gb-article-content">
        <?php
        // The article title is supplied by the styled header. Remove a duplicate
        // opening H1 if older WordPress content already contains one.
        $article_content = apply_filters( 'the_content', get_the_content() );
        $article_content = preg_replace( '/^\s*<h1\b[^>]*>.*?<\/h1>\s*/is', '', $article_content, 1 );
        echo $article_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
      </div>
    </article>
  <?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>
