<?php
/** Public song pages, derived from the published catalog. */
defined( 'ABSPATH' ) || exit;

function irl_song_slug( $title ) {
	$slug = preg_replace( '/[^a-zA-Z0-9]+/', '', remove_accents( $title ) );
	return $slug ?: 'Song';
}

/** Fill missing slugs once, retaining the URL spelling used before slug fields existed. */
function irl_catalog_slugs( $catalog ) {
	$used_releases = array();
	foreach ( $catalog['releases'] as $release ) {
		if ( isset( $release['slug'] ) && '' !== $release['slug'] ) { $used_releases[strtolower( $release['slug'] )] = true; }
	}
	foreach ( $catalog['releases'] as &$release ) {
		if ( ! isset( $release['slug'] ) || '' === $release['slug'] ) { $release['slug'] = irl_unique_slug( $release['title'], $release['id'], $used_releases ); }
		$used_tracks = array();
		foreach ( $release['tracks'] as $track ) {
			if ( isset( $track['slug'] ) && '' !== $track['slug'] ) { $used_tracks[strtolower( $track['slug'] )] = true; }
		}
		foreach ( $release['tracks'] as &$track ) {
			if ( ! isset( $track['slug'] ) || '' === $track['slug'] ) { $track['slug'] = irl_unique_slug( $track['title'], $track['id'], $used_tracks ); }
		}
		unset( $track );
	}
	unset( $release );
	return $catalog;
}

function irl_unique_slug( $title, $id, &$used ) {
	$base = irl_song_slug( $title ); $slug = $base; $suffix = 2;
	if ( isset( $used[strtolower( $slug )] ) ) { $slug = $base . '-' . $id; }
	while ( isset( $used[strtolower( $slug )] ) ) { $slug = $base . '-' . $id . '-' . $suffix++; }
	$used[strtolower( $slug )] = true;
	return $slug;
}

function irl_song_entries( $album ) {
	$entries = array();
	foreach ( $album['tracks'] as $track ) {
		$path = $track['releaseSlug'] . '/' . $track['slug'];
		$track['songPath'] = $path;
		$track['pageUrl'] = home_url( '/IanRaymond/Music/' . $path . '/' );
		$entries[] = $track;
	}
	return $entries;
}

function irl_song_media_url( $path ) {
	$base = 0 === strpos( $path, 'media/' ) ? irl_media()['url'] . '/' : plugin_dir_url( __FILE__ ) . 'player/';
	if ( 0 === strpos( $path, 'media/' ) ) { $path = substr( $path, 6 ); }
	if ( preg_match( '#^https?://#', $path ) ) { return $path; }
	return $base . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
}

/** Song URLs are not the blog home, so the theme's front-page title, canonical and Person data stay off them. */
add_action( 'parse_query', function ( $query ) {
	if ( $query->is_main_query() && $query->get( 'irl_song' ) ) { $query->is_home = false; }
} );

function irl_song_render( $requested ) {
	$album = irl_album();
	$entries = $album ? irl_song_entries( $album ) : array(); $found = null;
	foreach ( $entries as $i => $entry ) {
		if ( strtolower( $entry['songPath'] ) === strtolower( $requested ) ) { $found = $i; break; }
	}
	if ( null === $found ) {
		status_header( 404 ); nocache_headers();
		echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Song not found</title><h1>Song not found</h1><a href="' . esc_url( home_url( '/listen/' ) ) . '">Browse music</a></html>'; return;
	}
	$t = $entries[$found]; $next = count( $entries ) > 1 ? $entries[( $found + 1 ) % count( $entries )] : null;
	$title = $t['title'] . ' — Ian Raymond | ' . $t['releaseTitle'];
	$description = 'Listen to ' . $t['title'] . ' by Ian Raymond from ' . $t['releaseTitle'] . '. Stream the song and download the audio.';
	$audio = irl_song_media_url( $t['file'] );
	$wav = ! empty( $t['wav'] ) ? $t['wav'] : preg_replace( '/\.mp3$/i', '.wav', $t['file'] );
	$has_wav = irl_catalog_path( $wav, array( 'wav' ) );
	$art = ! empty( $t['artwork'] ) ? $t['artwork'] : $t['releaseArtwork']; $art = $art ? irl_song_media_url( $art ) : '';
	$css = plugin_dir_url( __FILE__ ) . 'player/assets/song.css?v=' . IRL_VERSION;
	// The song that reveals the secret track gets the trip (song-trip.js): it escalates with playback.
	$trip = ! empty( $album['secret']['revealDuringTrack'] ) && $album['secret']['revealDuringTrack'] === $t['id'] && ! empty( $album['secret']['track']['file'] );
	$trip_bpm = (int) ( $album['secret']['psychedelic']['bpm'] ?? 140 );
	$trip_css = plugin_dir_url( __FILE__ ) . 'player/assets/song-trip.css?v=' . IRL_VERSION;
	$head = function () use ( $t, $description, $title, $audio, $art ) {
		?>
<meta name="description" content="<?php echo esc_attr( $description ); ?>">
<link rel="canonical" href="<?php echo esc_url( $t['pageUrl'] ); ?>">
<meta property="og:type" content="music.song"><meta property="og:title" content="<?php echo esc_attr( $title ); ?>"><meta property="og:description" content="<?php echo esc_attr( $description ); ?>"><meta property="og:url" content="<?php echo esc_url( $t['pageUrl'] ); ?>">
<?php if ( $art ) { ?><meta property="og:image" content="<?php echo esc_url( $art ); ?>"><?php } ?>
<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'MusicRecording', 'name' => $t['title'], 'url' => $t['pageUrl'], 'byArtist' => array( '@type' => 'Person', 'name' => 'Ian Raymond' ), 'inAlbum' => array( '@type' => 'MusicAlbum', 'name' => $t['releaseTitle'] ), 'audio' => array( '@type' => 'AudioObject', 'contentUrl' => $audio, 'encodingFormat' => 'audio/mpeg' ) ), JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<?php
	};
	status_header( 200 ); header( 'Content-Type: text/html; charset=utf-8' );
	// Inside the site theme (header, navigation, footer) when it is available; otherwise a standalone page.
	$themed = function_exists( 'locate_template' ) && locate_template( array( 'header.php' ) ) && locate_template( array( 'footer.php' ) );
	if ( $themed ) {
		add_filter( 'pre_get_document_title', function () use ( $title ) { return $title; }, 99 );
		add_filter( 'ir_head_meta', '__return_false' ); // the theme's own description and share tags
		add_action( 'wp_head', $head );
		wp_enqueue_style( 'irl-song', $css, array(), null );
		if ( $trip ) { wp_enqueue_style( 'irl-song-trip', $trip_css, array(), null ); }
		get_header();
	} else {
		?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title><?php $head(); ?>
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
<?php if ( $trip ) { ?><link rel="stylesheet" href="<?php echo esc_url( $trip_css ); ?>"><?php } ?>
</head><body class="irl-song-standalone"><header class="irl-song-header"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">IAN RAYMOND</a><a href="<?php echo esc_url( home_url( '/listen/' ) ); ?>">All music</a></header>
		<?php
	}
	?>
<main id="main" class="irl-song"<?php if ( $trip ) { echo ' data-trip data-bpm="' . esc_attr( $trip_bpm ) . '"'; } ?>><div><?php if ( $art ) { ?><img class="artwork" src="<?php echo esc_url( $art ); ?>" alt="<?php echo esc_attr( $t['title'] . ' artwork' ); ?>"><?php } ?></div><article>
<p class="eyebrow"><a href="<?php echo esc_url( home_url( '/listen/' ) ); ?>"><?php echo esc_html( $t['releaseTitle'] ); ?></a></p><h1><?php echo esc_html( $t['title'] ); ?></h1><p class="artist">Ian Raymond</p>
<audio controls preload="metadata" src="<?php echo esc_url( $audio ); ?>" aria-label="<?php echo esc_attr( 'Listen to ' . $t['title'] ); ?>"></audio>
<div class="actions"><button type="button" id="copy-link">Copy streaming link</button><a href="<?php echo esc_url( $audio ); ?>" download>Download MP3</a>
<?php if ( $has_wav ) { ?><a href="<?php echo esc_url( irl_song_media_url( $wav ) ); ?>" download>Download WAV</a><?php } else { ?><span>WAV coming soon</span><?php } ?></div>
<label for="stream-link">Streaming link</label><input id="stream-link" readonly value="<?php echo esc_attr( $t['pageUrl'] ); ?>"><p id="copy-status" role="status"></p>
<?php foreach ( array( 'story' => 'About the song', 'lyrics' => 'Lyrics', 'credits' => 'Credits' ) as $key => $label ) { if ( ! empty( $t[$key] ) ) { ?><section><h2><?php echo esc_html( $label ); ?></h2><p class="notes"><?php echo esc_html( $t[$key] ); ?></p></section><?php } } ?>
<?php if ( $next ) { ?><nav class="next"><a rel="next" href="<?php echo esc_url( $next['pageUrl'] ); ?>">Next song: <?php echo esc_html( $next['title'] ); ?> →</a></nav><?php } ?>
</article></main><script src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'player/assets/song.js?v=' . IRL_VERSION ); ?>" defer></script>
<?php if ( $trip ) {
	$secret = $album['secret']; $note = $secret['note'] ?? array();
	echo '<script type="application/json" id="irl-secret">' . wp_json_encode( array(
		'revealAt' => (float) ( $secret['revealAtSeconds'] ?? 10 ), 'caption' => (string) ( $secret['caption'] ?? 'Click here' ),
		'title' => (string) ( $secret['track']['title'] ?? '' ), 'file' => irl_song_media_url( $secret['track']['file'] ),
		'noteTitle' => (string) ( $note['title'] ?? '' ), 'paragraphs' => $note['paragraphs'] ?? array(), 'closeLabel' => (string) ( $note['closeLabel'] ?? 'Close' ),
	), JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	?><script src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'player/assets/song-trip.js?v=' . IRL_VERSION ); ?>" defer></script><?php } ?>
	<?php
	if ( $themed ) { get_footer(); } else { echo '</body></html>'; }
}
