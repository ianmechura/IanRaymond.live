<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('editor-styles');
    add_editor_style('assets/editor.css');
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ir-site', get_theme_file_uri('assets/site.css'), [], filemtime(get_theme_file_path('assets/site.css')));
    wp_enqueue_script('ir-site', get_theme_file_uri('assets/site.js'), [], filemtime(get_theme_file_path('assets/site.js')), ['in_footer' => true, 'strategy' => 'defer']);
});
add_filter('pre_get_document_title', function ($title) {
    return is_front_page() ? 'Original Music by Ian Raymond' : $title;
});
function ir_setting($key, $fallback = '') {
    $settings = get_option('ir_settings', []);
    if (isset($settings[$key])) return $settings[$key];
    $defaults = function_exists('ir_defaults') ? ir_defaults() : [];
    return $defaults[$key] ?? $fallback;
}
function ir_asset($name) { return get_theme_file_uri('assets/' . $name); }
function ir_booking_url() { return 'mailto:' . sanitize_email(ir_setting('email', 'IanRaymondLive@gmail.com')) . '?subject=' . rawurlencode('Booking inquiry — Ian Raymond'); }
function ir_posts($type, $extra = []) {
    return get_posts(array_merge(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => 'menu_order date', 'order' => 'ASC'], $extra));
}
function ir_section_heading($title, $id, $label = '') {
    $prefix = $label ? esc_attr($label) . '.' : '';
    echo '<div class="section-heading"><h2 id="' . esc_attr($id) . '"' . ($label ? ' data-ir-content="' . $prefix . 'title"' : '') . '>' . esc_html($title) . '</h2></div>';
}
add_action('wp_head', function () {
    $description = 'Solo acoustic. All original. True stories told straight. Meet Ian Raymond, an outlaw country songwriter from Flower Mound, Texas. Music, booking, and dispatches.';
    if (is_singular('post')) $description = wp_strip_all_tags(get_the_excerpt());
    $meta = apply_filters('ir_head_meta', true); // pages with their own description/share tags (e.g. the player's song pages) turn this off
    if ($meta) echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta name="theme-color" content="#191a17">' . "\n";
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url(ir_asset('favicon.svg')) . '">' . "\n";
    if ($meta) echo '<meta property="og:title" content="' . esc_attr(wp_get_document_title()) . '"><meta property="og:description" content="' . esc_attr($description) . '"><meta property="og:type" content="website"><meta property="og:url" content="' . esc_url(is_singular() ? get_permalink() : home_url('/')) . '"><meta property="og:image" content="' . esc_url(ir_asset('root-pass.webp')) . '"><meta name="twitter:card" content="summary_large_image">';
    if (is_front_page()) {
        echo '<link rel="canonical" href="' . esc_url(home_url('/')) . '">';
        echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'Ian Raymond', 'url' => home_url('/'), 'jobTitle' => 'Singer-songwriter', 'sameAs' => array_values(array_filter([ir_setting('youtube'), ir_setting('tiktok')]))], JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
});
add_action('template_redirect', function () {
    if (isset($_GET['ir_press'])) {
        wp_safe_redirect(home_url('/epk/'), 301);
        exit;
    }
});
