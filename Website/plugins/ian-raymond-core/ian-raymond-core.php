<?php
/**
 * Plugin Name: Ian Raymond Core
 * Description: Your songs, shows, videos, and artist details. Content stays yours when you change themes.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/starter-content.php';
// Keep short, structured records in one straightforward form; dispatches use blocks.
add_filter('use_block_editor_for_post_type', function ($use, $type) {
    return in_array($type, ['ir_song', 'ir_show', 'ir_video'], true) ? false : $use;
}, 10, 2);

function ir_defaults() {
    return [
        'tagline' => 'True stories. Told straight.',
        'intro' => 'Solo acoustic songwriter from Flower Mound, Texas.',
        'location' => 'Flower Mound, Texas',
        'email' => 'IanRaymondLive@gmail.com',
        'youtube' => 'https://www.youtube.com/@Grimfolk1',
        'tiktok' => 'https://www.tiktok.com/@outlawianraymond',
        'songs_count' => '18+', 'views_count' => '80K+', 'videos_count' => '100+', 'singles_count' => '4',
        'story' => '<p>Ian Raymond is a Texas roots singer-songwriter whose life has never followed a particularly conventional path. He built a career in software and technology while spending the rest of his time building things, playing guitar, and writing songs. By the time Ian decided to pursue music and songwriting professionally, he had already spent decades breaking things, fixing what he could, learning from his mistakes, and figuring out the rest as he went.</p><p>A husband and father, Ian writes with an old-school sensibility: direct, story-driven, human, and more interested in saying something than fitting neatly into a genre. He has an independent streak and little interest in following the herd. His music reflects the same instinct: traditional enough to feel familiar, but irreverent, unpredictable, and comfortable wandering outside the lines. Sometimes that means writing songs that make people uncomfortable. Ian does not need everyone to like him, and he does not expect everyone to like his music.</p><p>Offstage, he is usually working on some mechanical or electronics project, playing guitar, or at a local dive bar with his wife singing karaoke and talking to whoever happens to be nearby. He likes places where strangers still talk, musicians trade songs, and a good night with new friends can turn into anything.</p><p>That mix of hands-on experience, nonconformity, independence, and egalitarianism is probably the closest thing Ian has to a formula, both in music and everywhere else.</p>',
        'quote' => 'I don\'t need everyone to like me, and I don\'t expect everyone to like my music.',
        'format' => 'Solo acoustic · all original', 'set_length' => '45–90 minutes',
        'coverage' => 'Dallas–Fort Worth & surrounding', 'availability' => 'Booking now',
        'booking_intro' => 'One guitar. A room full of stories. An original, story-driven show built for a listening room.',
        'benefits' => "A full set of original material\nFlexible 45-to-90-minute sets\nSelf-contained: guitar and rig\nA show that holds a listening room\nProfessional, on time, easy to work with",
        'press_pdf' => '',
    ];
}

add_action('init', function () {
    foreach (['ir_song' => ['Songs', 'Song', 'dashicons-album'], 'ir_show' => ['Shows', 'Show', 'dashicons-calendar-alt'], 'ir_video' => ['Videos', 'Video', 'dashicons-video-alt3']] as $type => $labels) {
        register_post_type($type, [
            'labels' => ['name' => $labels[0], 'singular_name' => $labels[1], 'add_new_item' => 'Add ' . $labels[1], 'edit_item' => 'Edit ' . $labels[1]],
            'public' => false, 'show_ui' => true, 'show_in_rest' => true,
            'menu_icon' => $labels[2], 'menu_position' => 21,
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes', 'revisions'],
        ]);
    }
});

function ir_meta_fields($type) {
    $sets = [
        'ir_song' => [
            'audio' => ['Audio file', 'audio', 'Upload an MP3, M4A, or OGG. Leave empty until the recording is ready.'],
            'lyric' => ['A line from the song', 'text', 'A short lyric for the song card and press kit.'],
            'status' => ['Release note', 'text', 'For example: Studio single forthcoming.'],
            'download' => ['Offer an audio download', 'checkbox', 'Shows a download link. Streaming files remain publicly accessible even when this is off.'],
        ],
        'ir_show' => [
            'date' => ['Show date', 'date', 'Past shows automatically leave the upcoming list.'],
            'time' => ['Doors / set time', 'text', 'Include the local time, e.g. 7:30 PM.'],
            'venue' => ['Venue', 'text', ''], 'city' => ['City', 'text', ''],
            'tickets' => ['Tickets or event link', 'url', 'Optional. Leave empty for a show without a ticket link.'],
        ],
        'ir_video' => [
            'url' => ['YouTube link or MP4 file', 'video', 'YouTube videos load only when a visitor clicks play. You can also select an uploaded MP4.'],
            'captions' => ['Captions file (WebVTT URL)', 'url', 'For self-hosted videos. YouTube manages its own captions.'],
        ],
    ];
    return $sets[$type] ?? [];
}
add_action('add_meta_boxes', function () {
    foreach (['ir_song', 'ir_show', 'ir_video'] as $type) {
        add_meta_box('ir_details', 'Details', function ($post) {
            wp_nonce_field('ir_save_details', 'ir_details_nonce');
            foreach (ir_meta_fields($post->post_type) as $key => [$label, $kind, $help]) {
                $value = get_post_meta($post->ID, '_ir_' . $key, true);
                echo '<p><label for="ir_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
                if ($kind === 'checkbox') {
                    echo '<input type="checkbox" id="ir_' . esc_attr($key) . '" name="ir_meta[' . esc_attr($key) . ']" value="1" ' . checked($value, '1', false) . '>';
                } else {
                    $input_type = in_array($kind, ['audio', 'video'], true) ? 'url' : $kind;
                    echo '<input class="widefat" type="' . esc_attr($input_type) . '" id="ir_' . esc_attr($key) . '" name="ir_meta[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
                    if (in_array($kind, ['audio', 'video'], true)) echo '<button type="button" class="button ir-media-button" data-target="ir_' . esc_attr($key) . '" data-kind="' . esc_attr($kind) . '">Choose / upload ' . esc_html($kind) . '</button>';
                }
                if ($help) echo '<span class="description">' . esc_html($help) . '</span>';
                echo '</p>';
            }
            echo '<p class="description">Use the main editor for a short description. Use Order under Page Attributes to control the display order.</p>';
        }, $type, 'normal', 'high');
    }
});
function ir_valid_date($date) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}
add_action('save_post', function ($id) {
    if (!isset($_POST['ir_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ir_details_nonce'])), 'ir_save_details')) return;
    if (!current_user_can('edit_post', $id) || wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    $input = isset($_POST['ir_meta']) && is_array($_POST['ir_meta']) ? wp_unslash($_POST['ir_meta']) : [];
    foreach (ir_meta_fields(get_post_type($id)) as $key => [$label, $kind]) {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : '';
        if ($kind === 'checkbox') $value = $value === '1' ? '1' : '0';
        elseif (in_array($kind, ['audio', 'video', 'url'], true)) $value = esc_url_raw($value, ['http', 'https']);
        elseif ($kind === 'date') $value = ir_valid_date($value) ? $value : '';
        else $value = sanitize_text_field($value);
        update_post_meta($id, '_ir_' . $key, $value);
    }
});

function ir_settings_groups() {
    return [
        'The essentials' => ['tagline' => 'Headline', 'intro' => 'Introduction', 'location' => 'Home base', 'email' => 'Booking & press email', 'youtube' => 'YouTube URL', 'tiktok' => 'TikTok URL'],
        'The story' => ['story' => 'Biography', 'quote' => 'Pull quote'],
        'Booking' => ['availability' => 'Availability', 'booking_intro' => 'Booking introduction', 'format' => 'Show format', 'set_length' => 'Set length', 'coverage' => 'Travel coverage'],
        'Press kit' => ['press_pdf' => 'Downloadable PDF URL (leave empty for the included kit)'],
    ];
}
function ir_sanitize_settings($input) {
    $clean = [];
    foreach (ir_defaults() as $key => $default) {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : $default;
        if ($key === 'story') $clean[$key] = wp_kses_post($value);
        elseif ($key === 'benefits') $clean[$key] = sanitize_textarea_field($value);
        elseif ($key === 'email') $clean[$key] = sanitize_email($value);
        elseif (in_array($key, ['youtube', 'tiktok', 'press_pdf'], true)) $clean[$key] = esc_url_raw($value, ['https', 'http']);
        else $clean[$key] = sanitize_text_field($value);
    }
    return $clean;
}
add_action('admin_init', function () {
    register_setting('ir_settings_group', 'ir_settings', ['sanitize_callback' => 'ir_sanitize_settings', 'default' => ir_defaults()]);
});
add_action('admin_menu', function () {
    add_menu_page('Artist Settings', 'Artist Settings', 'manage_options', 'ir-settings', function () {
        if (!current_user_can('manage_options')) return;
        $values = array_merge(ir_defaults(), get_option('ir_settings', []));
        echo '<div class="wrap"><h1>Ian Raymond · Artist Settings</h1><p>Your website, in your own words. Songs, Shows, and Videos each have their own menu. Use Posts for your dispatch.</p>';
        settings_errors();
        echo '<form action="options.php" method="post">'; settings_fields('ir_settings_group');
        foreach (ir_settings_groups() as $group => $fields) {
            echo '<h2>' . esc_html($group) . '</h2><table class="form-table" role="presentation">';
            foreach ($fields as $key => $label) {
                echo '<tr><th scope="row"><label for="ir_setting_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
                if ($key === 'story') wp_editor($values[$key], 'ir_setting_story', ['textarea_name' => 'ir_settings[story]', 'textarea_rows' => 9, 'media_buttons' => false]);
                elseif (in_array($key, ['intro', 'benefits', 'booking_intro'], true)) echo '<textarea class="large-text" rows="4" id="ir_setting_' . esc_attr($key) . '" name="ir_settings[' . esc_attr($key) . ']">' . esc_textarea($values[$key]) . '</textarea>';
                else {
                    $type = $key === 'email' ? 'email' : (in_array($key, ['youtube', 'tiktok', 'press_pdf'], true) ? 'url' : 'text');
                    echo '<input class="regular-text" type="' . esc_attr($type) . '" id="ir_setting_' . esc_attr($key) . '" name="ir_settings[' . esc_attr($key) . ']" value="' . esc_attr($values[$key]) . '">';
                    if ($key === 'press_pdf') echo ' <button type="button" class="button ir-media-button" data-kind="application/pdf" data-target="ir_setting_press_pdf">Choose / upload PDF</button><p class="description">After changing your bio or songs, open Print press kit on the site, save it as PDF, then upload the new copy here.</p>';
                }
                echo '</td></tr>';
            }
            echo '</table>';
        }
        submit_button('Save artist details'); echo '</form>';
        if (!get_option('ir_seed_version')) {
            echo '<hr><h2>Start with your press kit</h2><p>Import the four songs from your supplied press kit and a draft dispatch. Existing artist settings are preserved.</p><form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="ir_seed">';
            wp_nonce_field('ir_seed'); submit_button('Import starter songs', 'secondary'); echo '</form>';
        }
        echo '</div>';
    }, 'dashicons-microphone', 20);
});
add_action('admin_post_ir_seed', function () {
    if (!current_user_can('manage_options')) wp_die('Not authorized.', '', ['response' => 403]);
    check_admin_referer('ir_seed');
    $result = ir_install_starter_content();
    if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
    wp_safe_redirect(admin_url('admin.php?page=ir-settings')); exit;
});
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($hook !== 'toplevel_page_ir-settings' && !in_array($screen->post_type ?? '', ['ir_song', 'ir_video'], true)) return;
    wp_enqueue_media();
    wp_enqueue_script('ir-admin', plugin_dir_url(__FILE__) . 'admin.js', ['media-editor'], '1.0.0', true);
});
add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('ir_welcome', 'Your artist website', function () {
        echo '<p><strong>Keep the stories coming.</strong></p><ul>';
        foreach (['post-new.php?post_type=ir_song' => 'Add a song or upload a recording', 'post-new.php?post_type=ir_show' => 'Add an upcoming show', 'post-new.php' => 'Write a dispatch', 'post-new.php?post_type=ir_video' => 'Add a video', 'admin.php?page=ir-settings' => 'Update your bio, numbers, booking, or press kit'] as $path => $label) echo '<li><a href="' . esc_url(admin_url($path)) . '">' . esc_html($label) . '</a></li>';
        echo '</ul><p>Only published entries appear on the website. Song download links are off until you enable them.</p>';
    });
});

function ir_youtube_id($url) {
    $host = strtolower(wp_parse_url($url, PHP_URL_HOST) ?? '');
    $path = wp_parse_url($url, PHP_URL_PATH) ?? '';
    $id = '';
    if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) $id = trim($path, '/');
    elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        parse_str(wp_parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $id = $query['v'] ?? '';
        if (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})~', $path, $match)) $id = $match[1];
    }
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : '';
}
