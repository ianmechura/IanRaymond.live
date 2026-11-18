<?php
defined('ABSPATH') || exit;
function ir_install_starter_content() {
if (get_option('ir_seed_version')) return false;
if (get_option('ir_settings', null) === null) add_option('ir_settings', ir_defaults());
$songs = [
    ['Son of a Gun', 'A devil, a madam & the gunfighter who buried his pistols for his boy.', 'He was faster than the devil / she was hotter than the sun…'],
    ['That Old Dodge', 'First truck, first freedom — and passing it on down the line.', 'Two kids on a bench seat in a rusted truck…'],
    ['Hoe a Row', 'What your folks plant in you, and the weeds you have to dig out.', 'Your folks plant the seeds / some grow fruit and some grow weeds…'],
    ['Texas Tears', 'A family split apart under the flag that is supposed to protect it.', 'It doesn’t feel like freedom / if they can just take it all away…'],
];
foreach ($songs as $i => [$title, $description, $lyric]) {
    $id = wp_insert_post(['post_type' => 'ir_song', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $description, 'menu_order' => $i], true);
    if (is_wp_error($id)) return $id;
    update_post_meta($id, '_ir_lyric', $lyric);
    update_post_meta($id, '_ir_status', 'Studio single forthcoming');
    update_post_meta($id, '_ir_download', '0');
}
// Drafts give the artist starting points without implying recent news or fake dates.
wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'From the writing room', 'post_content' => '<!-- wp:paragraph --><p>Start your next dispatch here. Tell people what you’re writing, where you’re playing, or the story behind a song.</p><!-- /wp:paragraph -->']);
update_option('ir_seed_version', '1.0.0');
return true;

}
