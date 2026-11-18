<?php
get_header();
$songs = ir_posts('ir_song', ['numberposts' => -1]);
$shows = ir_posts('ir_show', ['meta_key' => '_ir_date', 'orderby' => 'meta_value', 'meta_query' => [['key' => '_ir_date', 'value' => wp_date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE']]]);
$videos = ir_posts('ir_video', ['orderby' => 'menu_order date', 'order' => 'DESC']);
$dispatches = ir_posts('post', ['numberposts' => 6, 'orderby' => 'date', 'order' => 'DESC']);
$pdf = ir_setting('press_pdf') ?: ir_asset('Ian-Raymond-EPK.pdf');
// The song list duplicates what Videos already shows/plays; kept in code, hidden from the page.
$ir_show_song_list = false;
?>
<main id="main">
<section class="hero wrap" aria-labelledby="artist-name">
    <h1 id="artist-name" data-ir-content="hero.artist_name">IAN RAYMOND<span class="name-period">.</span></h1>
    <div class="hero-copy"><p class="hero-intro" data-ir-content="hero.intro"><?php echo esc_html(ir_setting('intro')); ?></p><div class="hero-actions"><a class="text-link" href="<?php echo esc_url($pdf); ?>" download data-ir-content="hero.cta_press_kit">Download press kit <span aria-hidden="true">↓</span></a></div><div class="hero-footnote"><span class="status-dot"></span> <span data-ir-content="hero.availability"><?php echo esc_html(ir_setting('availability')); ?></span> <span class="muted">/</span> <span data-ir-content="hero.coverage"><?php echo esc_html(ir_setting('coverage')); ?></span></div></div>
</section>
<?php /* Hero "Meet the songs" CTA and Root Pass hero image removed for a more minimalist layout; markup preserved in git history if wanted back. */ ?>

<section id="story" class="section wrap story-section" aria-labelledby="story-title">
    <h2 id="story-title" class="sr-only">About</h2>
    <div class="story-body prose"><div data-ir-content="story.biography"><?php echo wp_kses_post(wpautop(ir_setting('story'))); ?></div><blockquote>“<span data-ir-content="story.pull_quote"><?php echo esc_html(ir_setting('quote')); ?></span>”<cite>— IAN RAYMOND</cite></blockquote><span class="coordinates" data-ir-content="story.location"><?php echo esc_html(ir_setting('location')); ?></span></div>
</section>

<section id="videos" class="section wrap videos-section" aria-labelledby="videos-title">
    <div class="heading-row"><?php ir_section_heading('Videos', 'videos-title', 'videos'); ?><a class="text-link" href="<?php echo esc_url(ir_setting('youtube')); ?>" target="_blank" rel="noopener noreferrer" data-ir-content="videos.cta_youtube">YouTube <span aria-hidden="true">↗</span></a></div>
    <?php if ($videos): ?><div class="video-grid"><?php foreach ($videos as $video): $url = get_post_meta($video->ID, '_ir_url', true); $yt = function_exists('ir_youtube_id') ? ir_youtube_id($url) : ''; ?><article class="video-card"><?php if ($yt): ?><div class="youtube-player" data-youtube="<?php echo esc_attr($yt); ?>" data-title="<?php echo esc_attr($video->post_title); ?>"><button class="video-play" aria-label="Play <?php echo esc_attr($video->post_title); ?> on YouTube"><?php if (has_post_thumbnail($video->ID)): ?><?php echo get_the_post_thumbnail($video->ID, 'large', ['loading' => 'lazy', 'alt' => '']); ?><?php else: ?><img src="<?php echo esc_url('https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg'); ?>" loading="lazy" alt=""><?php endif; ?><span class="play-circle" aria-hidden="true">▶</span></button></div><noscript><a href="<?php echo esc_url($url); ?>">Watch on YouTube ↗</a></noscript><?php elseif ($url && preg_match('/\.mp4(?:\?|$)/i', $url)): ?><video controls playsinline preload="none" <?php if (has_post_thumbnail($video->ID)) echo 'poster="' . esc_url(get_the_post_thumbnail_url($video->ID, 'large')) . '"'; ?>><source src="<?php echo esc_url($url); ?>" type="video/mp4"><?php if ($captions = get_post_meta($video->ID, '_ir_captions', true)): ?><track kind="captions" src="<?php echo esc_url($captions); ?>" srclang="en" label="English"><?php endif; ?>Your browser does not support video playback.</video><?php elseif ($url): ?><a class="button" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Watch video ↗</a><?php endif; ?><h3><?php echo esc_html($video->post_title); ?></h3><div class="muted"><?php echo wp_kses_post(wpautop($video->post_content)); ?></div></article><?php endforeach; ?></div>
    <?php else: ?><a class="channel-banner" href="<?php echo esc_url(ir_setting('youtube')); ?>" target="_blank" rel="noopener noreferrer"><span class="play-circle" aria-hidden="true">▶</span><span><strong data-ir-content="videos.banner_title">Ian Raymond on YouTube</strong><span data-ir-content="videos.banner_subtitle">Original music and acoustic performances</span></span><span class="channel-arrow" aria-hidden="true">↗</span></a><?php endif; ?>
</section>

<?php if ($ir_show_song_list): ?>
<section id="music" class="music-section section" aria-labelledby="music-title"><div class="wrap">
    <div class="heading-row"><?php ir_section_heading('Music', 'music-title', 'music'); ?></div>
    <div class="music-grid"><figure class="record-art"><img src="<?php echo esc_url(ir_asset('root-pass-steel.webp')); ?>" width="1254" height="1254" loading="lazy" alt="Root Pass artwork: Ian Raymond's name cut into a weathered steel plate, upcoming release" data-ir-content="music.record_art_image"><figcaption><span data-ir-content="music.record_art_caption_line1">IAN RAYMOND</span><span data-ir-content="music.record_art_caption_line2">ROOT PASS · UPCOMING RELEASE</span></figcaption></figure>
    <div class="track-list">
    <?php foreach ($songs as $i => $song): $audio = get_post_meta($song->ID, '_ir_audio', true); $lyric = get_post_meta($song->ID, '_ir_lyric', true); $slug = 'music.track_' . ($i + 1) . '_' . sanitize_title($song->post_title); ?>
        <article class="track"><div class="track-number"><?php echo esc_html(str_pad($i + 1, 2, '0', STR_PAD_LEFT)); ?></div><div class="track-content"><div class="track-heading"><h3 data-ir-content="<?php echo esc_attr($slug); ?>.title"><?php echo esc_html($song->post_title); ?></h3><span class="track-label" data-ir-content="<?php echo esc_attr($slug); ?>.label"><?php echo esc_html($audio ? 'LISTEN' : (get_post_meta($song->ID, '_ir_status', true) ?: 'ORIGINAL SONG')); ?></span></div><div class="track-description" data-ir-content="<?php echo esc_attr($slug); ?>.description"><?php echo wp_kses_post(wpautop($song->post_content)); ?></div><?php if ($lyric): ?><p class="lyric" data-ir-content="<?php echo esc_attr($slug); ?>.lyric">“<?php echo esc_html($lyric); ?>”</p><?php endif; ?>
        <?php if ($audio): ?><audio controls preload="none" aria-label="Listen to <?php echo esc_attr($song->post_title); ?>" <?php if (!get_post_meta($song->ID, '_ir_download', true)) echo 'controlslist="nodownload"'; ?> src="<?php echo esc_url($audio); ?>">Your browser does not support audio playback.</audio><?php if (get_post_meta($song->ID, '_ir_download', true) === '1'): ?><a class="download-track" href="<?php echo esc_url($audio); ?>" download>Download recording ↓</a><?php endif; ?><?php endif; ?>
        </div></article>
    <?php endforeach; ?>
    <?php if (!$songs): ?><p class="empty-note" data-ir-content="music.no_songs_message">The next story is taking shape. Hear Ian on YouTube.</p><?php endif; ?>
    <a class="text-link music-channel" href="<?php echo esc_url(ir_setting('youtube')); ?>" target="_blank" rel="noopener noreferrer" data-ir-content="music.cta_youtube">Hear more on YouTube <span aria-hidden="true">↗</span></a>
    </div></div>
</div></section>
<?php endif; ?>

<section id="booking" class="section wrap booking-section" aria-labelledby="booking-title">
    <div class="booking-grid"><div><?php ir_section_heading('Booking', 'booking-title', 'booking'); ?><p class="booking-intro" data-ir-content="booking.intro"><?php echo esc_html(ir_setting('booking_intro')); ?></p><a class="contact-email" href="<?php echo esc_url(ir_booking_url()); ?>" data-ir-content="booking.cta"><?php echo esc_html(ir_setting('email')); ?></a></div>
    <div class="booking-sheet"><dl><?php foreach (['format' => 'Format', 'set_length' => 'Set length', 'location' => 'Home base', 'coverage' => 'On the road'] as $key => $label): ?><div><dt><?php echo esc_html($label); ?></dt><dd data-ir-content="booking.<?php echo esc_attr($key); ?>"><?php echo esc_html(ir_setting($key)); ?></dd></div><?php endforeach; ?></dl><div class="sheet-bottom"><span class="status-dot"></span> <span data-ir-content="booking.availability"><?php echo esc_html(ir_setting('availability')); ?></span></div></div></div>
</section>

<section id="dispatch" class="section dispatch-section" aria-labelledby="dispatch-title"><div class="wrap">
    <div class="heading-row"><?php ir_section_heading('Updates', 'dispatch-title', 'dispatch'); ?></div>
    <div class="shows-panel"><div><h3 data-ir-content="dispatch.calendar_title">Upcoming shows</h3></div><div class="show-list">
    <?php if (!$shows): ?><p data-ir-content="dispatch.no_shows_message">No shows currently scheduled.</p><?php endif; ?>
    <?php foreach ($shows as $show): $date = get_post_meta($show->ID, '_ir_date', true); $ticket = get_post_meta($show->ID, '_ir_tickets', true); ?><article class="show"><time datetime="<?php echo esc_attr($date); ?>"><?php echo esc_html(wp_date('M j, Y', strtotime($date . ' 12:00:00'))); ?></time><div><h4><?php echo esc_html(get_post_meta($show->ID, '_ir_venue', true) ?: $show->post_title); ?></h4><p><?php echo esc_html(implode(' · ', array_filter([get_post_meta($show->ID, '_ir_city', true), get_post_meta($show->ID, '_ir_time', true)]))); ?></p><div class="show-note"><?php echo wp_kses_post(wpautop($show->post_content)); ?></div></div><?php if ($ticket): ?><a href="<?php echo esc_url($ticket); ?>" target="_blank" rel="noopener noreferrer">Details ↗</a><?php endif; ?></article><?php endforeach; ?>
    </div></div>
    <div class="dispatch-grid"><?php foreach ($dispatches as $post): setup_postdata($post); ?><article class="dispatch-card"><?php if (has_post_thumbnail()): ?><a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail('medium_large', ['loading' => 'lazy']); ?></a><?php endif; ?><div class="dispatch-meta eyebrow"><span><?php $cats = get_the_category(); echo esc_html($cats ? $cats[0]->name : 'Dispatch'); ?></span><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('m.d.Y')); ?></time></div><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?><span aria-hidden="true">↗</span></a></h3><p><?php echo esc_html(get_the_excerpt()); ?></p></article><?php endforeach; wp_reset_postdata(); ?><?php if (!$dispatches): ?><p class="empty-note" data-ir-content="dispatch.no_posts_message">Updates on new music and shows will be posted here.</p><?php endif; ?></div>
</div></section>

<section id="contact" class="contact-section" aria-labelledby="contact-title"><div class="wrap"><h2 id="contact-title" data-ir-content="contact.headline">Booking and press inquiries</h2><a class="contact-email" href="<?php echo esc_url(ir_booking_url()); ?>" data-ir-content="contact.email"><?php echo esc_html(ir_setting('email')); ?><span aria-hidden="true">↗</span></a><div class="contact-bottom"><p><span data-ir-content="contact.location"><?php echo esc_html(ir_setting('location')); ?></span> <span aria-hidden="true">✦</span> <span data-ir-content="contact.coverage"><?php echo esc_html(ir_setting('coverage')); ?></span></p><div><a href="<?php echo esc_url(ir_setting('youtube')); ?>" target="_blank" rel="noopener noreferrer" data-ir-content="contact.link_youtube">YouTube ↗</a><a href="<?php echo esc_url(ir_setting('tiktok')); ?>" target="_blank" rel="noopener noreferrer" data-ir-content="contact.link_tiktok">TikTok ↗</a><a href="<?php echo esc_url($pdf); ?>" download data-ir-content="contact.link_press_kit">Press kit ↓</a></div></div></div></section>
</main>
<?php get_footer(); ?>
