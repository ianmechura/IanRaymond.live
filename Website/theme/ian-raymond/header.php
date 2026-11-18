<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header wrap">
    <a class="wordmark" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Ian Raymond home">IR<span>·</span></a>
    <span class="header-location" data-ir-content="header.location_tagline">ROOTS, FOLK AND OUTLAW <span class="small-star">✦</span> FLOWER MOUND, TX</span>
    <button class="menu-toggle" aria-expanded="false" aria-controls="main-nav">Menu <span aria-hidden="true">+</span></button>
    <nav id="main-nav" aria-label="Main navigation">
        <?php // 'music' nav key kept for data-ir-content continuity, relabeled "Videos" and pointed at #videos since the song-list section is hidden (see front-page.php $ir_show_song_list). ?>
        <?php foreach (['story' => ['story', 'The story'], 'music' => ['videos', 'Videos'], 'dispatch' => ['dispatch', 'Dispatch']] as $key => [$anchor, $label]): ?>
        <a href="<?php echo esc_url(home_url('/#' . $anchor)); ?>" data-ir-content="header.nav_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
        <a class="nav-book" href="<?php echo esc_url(home_url('/#booking')); ?>" data-ir-content="header.nav_book_cta">Book a show <span aria-hidden="true">↗</span></a>
    </nav>
</header>
