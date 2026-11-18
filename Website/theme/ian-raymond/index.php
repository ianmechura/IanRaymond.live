<?php get_header(); ?>
<main id="main" class="wrap article-page">
<?php if (have_posts()): while (have_posts()): the_post(); ?>
<article <?php post_class(); ?>><p class="eyebrow">THE DISPATCH / <?php echo esc_html(get_the_date('m.d.Y')); ?></p><h1><?php the_title(); ?></h1><?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?><div class="prose"><?php the_content(); ?></div></article>
<?php endwhile; the_posts_pagination(); else: ?><h1>Nothing here yet.</h1><p>There’s a story waiting back home.</p><?php endif; ?>
<a class="text-link" href="<?php echo esc_url(home_url('/#dispatch')); ?>">← Back to the dispatch</a>
</main>
<?php get_footer(); ?>
