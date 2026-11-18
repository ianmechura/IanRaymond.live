<?php
/** Release catalog, retaining the original album as the initial release. */
defined( 'ABSPATH' ) || exit;

function irl_catalog() {
	$saved = get_option( 'irl_catalog', null );
	if ( is_array( $saved ) && isset( $saved['releases'] ) ) {
		return irl_catalog_slugs( $saved );
	}
	$album = irl_base_album();
	return irl_catalog_slugs( array( 'releases' => $album ? array( array(
		'id' => 'root-pass', 'type' => 'album', 'title' => $album['title'],
		'artwork' => $album['artwork'] ?? '', 'artworkId' => 0, 'tracks' => $album['tracks'],
	) ) : array() ) );
}

function irl_catalog_revision( $catalog ) {
	return hash( 'sha256', wp_json_encode( $catalog ) );
}

/** Enrich the original player configuration with ordered release sections. */
function irl_album() {
	$album = irl_base_album();
	if ( ! $album ) { return null; }
	$catalog = irl_catalog();
	$album['tracks'] = array();
	$album['releases'] = $catalog['releases'];
	$root = false;
	foreach ( $catalog['releases'] as $release ) {
		foreach ( $release['tracks'] as $index => $track ) {
			$track['releaseId'] = $release['id'];
			$track['releaseTitle'] = $release['title'];
			$track['releaseSlug'] = $release['slug'];
			$track['releaseArtwork'] = $release['artwork'];
			$track['releaseNumber'] = $index + 1;
			$album['tracks'][] = $track;
		}
		if ( 'root-pass' === $release['id'] ) { $root = $release; }
	}
	$first = $catalog['releases'][0] ?? null;
	$album['title'] = count( $catalog['releases'] ) > 1 ? 'Music' : ( $first['title'] ?? 'Music' );
	$album['artwork'] = $first['artwork'] ?? '';
	$album['subtitle'] = '';
	$album['edition'] = 'Albums & singles';
	if ( $root && isset( $album['secret']['track'] ) ) {
		$album['secret']['track']['releaseId'] = $root['id'];
		$album['secret']['track']['releaseTitle'] = $root['title'];
		$album['secret']['track']['releaseArtwork'] = $root['artwork'];
		$album['secret']['track']['releaseNumber'] = count( $root['tracks'] ) + 1;
	} else {
		unset( $album['secret'] );
	}
	if ( function_exists( 'irl_epk_main' ) && irl_epk_main() ) { $album['epk']['url'] = irl_epk_url(); } // Settings → Ian Raymond Press Kits
	return $album;
}

/** Only local player media paths can be saved as song or legacy artwork references. */
function irl_catalog_path( $value, $extensions ) {
	if ( ! is_string( $value ) || ! preg_match( '#^(media|content)/(.+)$#D', $value, $parts ) ||
		preg_match( '#(^|/)[.]{1,2}(/|$)|[\\\\\x00-\x1f]#', $parts[2] ) ) { return false; }
	if ( ! in_array( strtolower( pathinfo( $value, PATHINFO_EXTENSION ) ), $extensions, true ) ) { return false; }
	$base = 'media' === $parts[1] ? irl_media()['path'] : __DIR__ . '/player/content';
	$path = realpath( $base . '/' . $parts[2] );
	$real_base = realpath( $base );
	return $path && $real_base && 0 === strpos( $path, $real_base . DIRECTORY_SEPARATOR ) && is_file( $path ) ? $value : false;
}

/** Empty slugs are generated for new entries; explicit segments must be safe and unique. */
function irl_validate_slug( $slug, &$used ) {
	if ( '' === $slug ) { return true; }
	if ( ! is_string( $slug ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,400}$/D', $slug ) || isset( $used[strtolower( $slug )] ) ) { return false; }
	$used[strtolower( $slug )] = true;
	return true;
}

function irl_save_catalog( WP_REST_Request $req ) {
	$body = $req->get_json_params();
	$current = irl_catalog();
	if ( ! is_array( $body ) || ( $body['revision'] ?? '' ) !== irl_catalog_revision( $current ) ) {
		return new WP_Error( 'irl_conflict', 'The releases changed in another session. Reload before saving.', array( 'status' => 409 ) );
	}
	$releases = $body['releases'] ?? null;
	if ( ! is_array( $releases ) || ! count( $releases ) || count( $releases ) > 100 ) {
		return new WP_Error( 'irl_catalog', 'Add at least one release (up to 100).', array( 'status' => 400 ) );
	}
	$existing_releases = array_column( $current['releases'], null, 'id' );
	$existing_tracks = array();
	foreach ( $current['releases'] as $release ) {
		foreach ( $release['tracks'] as $track ) { $existing_tracks[$track['id']] = $track; }
	}
	$release_slugs = array();
	$clean = array(); $ids = array(); $track_ids = array(); $total = 0;
	foreach ( $releases as $r ) {
		if ( ! is_array( $r ) ) { return new WP_Error( 'irl_release', 'Invalid release.', array( 'status' => 400 ) ); }
		$id = $r['id'] ?? ''; $title = sanitize_text_field( $r['title'] ?? '' );
		if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9-]{1,80}$/D', $id ) || isset( $ids[$id] ) || '' === $title || strlen( $title ) > 255 || ! in_array( $r['type'] ?? '', array( 'album', 'single' ), true ) ) {
			return new WP_Error( 'irl_release', 'Each release needs a unique ID, name and Album or Single type.', array( 'status' => 400 ) );
		}
		$slug = ( $r['slug'] ?? '' ) === '' ? ( $existing_releases[$id]['slug'] ?? '' ) : $r['slug'];
		if ( ! irl_validate_slug( $slug, $release_slugs ) ) { return new WP_Error( 'irl_slug', 'Release URL slugs must be unique and contain only letters, numbers, hyphens or underscores.', array( 'status' => 400 ) ); }
		$ids[$id] = true;
		$art_id = absint( $r['artworkId'] ?? 0 ); $art = '';
		if ( $art_id ) {
			$art = wp_attachment_is_image( $art_id ) ? wp_get_attachment_image_url( $art_id, 'large' ) : false;
			if ( ! $art ) { return new WP_Error( 'irl_art', 'Choose an image from the media library.', array( 'status' => 400 ) ); }
		} elseif ( ! empty( $r['artwork'] ) ) {
			$art = irl_catalog_path( $r['artwork'], array( 'jpg', 'jpeg', 'png', 'webp' ) );
			if ( false === $art ) { return new WP_Error( 'irl_art', 'Release artwork is missing. Choose its cover again.', array( 'status' => 400 ) ); }
		}
		if ( empty( $r['tracks'] ) || ! is_array( $r['tracks'] ) ) { return new WP_Error( 'irl_tracks', 'Upload at least one song for each release before saving.', array( 'status' => 400 ) ); }
		$tracks = array(); $song_slugs = array();
		foreach ( $r['tracks'] as $t ) {
			if ( ! is_array( $t ) || ++$total > 1000 ) { return new WP_Error( 'irl_tracks', 'Invalid song list (up to 1,000 songs).', array( 'status' => 400 ) ); }
			$tid = $t['id'] ?? ''; $name = sanitize_text_field( $t['title'] ?? '' );
			$file = irl_catalog_path( $t['file'] ?? '', array( 'mp3' ) );
			if ( ! is_string( $tid ) || ! preg_match( '/^[a-z0-9-]{1,100}$/D', $tid ) || isset( $track_ids[$tid] ) || 'naked-billy-b-side-remix' === $tid || '' === $name || strlen( $name ) > 255 || ! $file ) {
				return new WP_Error( 'irl_track', 'Each song needs a unique ID, title and uploaded MP3.', array( 'status' => 400 ) );
			}
			$track_slug = ( $t['slug'] ?? '' ) === '' ? ( $existing_tracks[$tid]['slug'] ?? '' ) : $t['slug'];
			if ( ! irl_validate_slug( $track_slug, $song_slugs ) ) { return new WP_Error( 'irl_slug', 'Song URL slugs must be unique within their release and contain only letters, numbers, hyphens or underscores.', array( 'status' => 400 ) ); }
			$track_ids[$tid] = true;
			$wav = empty( $t['wav'] ) ? '' : irl_catalog_path( $t['wav'], array( 'wav' ) );
			if ( false === $wav ) { return new WP_Error( 'irl_wav', 'The WAV file is missing. Upload it again.', array( 'status' => 400 ) ); }
			$track_art = empty( $t['artwork'] ) ? '' : irl_catalog_path( $t['artwork'], array( 'jpg', 'jpeg', 'png', 'webp' ) );
			if ( false === $track_art ) { return new WP_Error( 'irl_art', 'A song artwork file is missing.', array( 'status' => 400 ) ); }
			$track = array( 'id' => $tid, 'title' => $name, 'slug' => $track_slug, 'file' => $file, 'wav' => $wav, 'artwork' => $track_art );
			foreach ( array( 'story', 'lyrics', 'credits' ) as $field ) { $track[$field] = sanitize_textarea_field( $t[$field] ?? '' ); }
			$tracks[] = $track;
		}
		$clean[] = array( 'id' => $id, 'type' => $r['type'], 'title' => $title, 'slug' => $slug, 'artworkId' => $art_id, 'artwork' => $art, 'tracks' => $tracks );
	}
	$catalog = irl_catalog_slugs( array( 'releases' => $clean ) );
	$saved = update_option( 'irl_catalog', $catalog, false );
	if ( ! $saved && get_option( 'irl_catalog', null ) !== $catalog ) {
		return new WP_Error( 'irl_save', 'The releases could not be saved. Please try again.', array( 'status' => 500 ) );
	}
	if ( $saved ) { do_action( 'irl_catalog_saved', $catalog, $current ); }
	return array( 'catalog' => $catalog, 'revision' => irl_catalog_revision( $catalog ) );
}

/** Stage a new MP3 without changing the published catalog; unique names prevent collisions. */
function irl_catalog_upload( WP_REST_Request $req ) {
	$name = sanitize_file_name( (string) $req->get_param( 'name' ) );
	$token = (string) $req->get_param( 'token' );
	$index = (int) $req->get_param( 'index' ); $total = (int) $req->get_param( 'total' );
	$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$files = $req->get_file_params(); $chunk_size = 1024 * 1024;
	if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) || ! in_array( $extension, array( 'mp3', 'wav' ), true ) || empty( $files['chunk']['tmp_name'] ) || $total < 1 || $total > 512 || $index < 0 || $index >= $total ) {
		return new WP_Error( 'irl_upload', 'Choose an MP3 or WAV under 512 MB.', array( 'status' => 400 ) );
	}
	$chunk = file_get_contents( $files['chunk']['tmp_name'] );
	if ( false === $chunk || strlen( $chunk ) > $chunk_size || ! strlen( $chunk ) || ( $index < $total - 1 && strlen( $chunk ) !== $chunk_size ) ) {
		return new WP_Error( 'irl_chunk', 'The upload chunk is incomplete. Try uploading the song again.', array( 'status' => 400 ) );
	}
	$m = irl_media(); wp_mkdir_p( $m['path'] );
	$name = $token . '-' . $name; $part = $m['path'] . '/.' . $name . '.part'; $dest = $m['path'] . '/' . $name;
	if ( file_exists( $dest ) ) { return new WP_Error( 'irl_exists', 'This upload is already complete.', array( 'status' => 409 ) ); }
	$fp = fopen( $part, 'c+b' );
	if ( ! $fp ) { return new WP_Error( 'irl_write', 'Could not create the upload file.', array( 'status' => 500 ) ); }
	flock( $fp, LOCK_EX );
	$size = fstat( $fp )['size'];
	if ( $size !== $index * $chunk_size ) {
		flock( $fp, LOCK_UN ); fclose( $fp );
		return new WP_Error( 'irl_order', 'Upload chunks arrived out of order. Try uploading the song again.', array( 'status' => 409 ) );
	}
	fseek( $fp, 0, SEEK_END ); $written = fwrite( $fp, $chunk ); fflush( $fp ); flock( $fp, LOCK_UN ); fclose( $fp );
	if ( $written !== strlen( $chunk ) ) { return new WP_Error( 'irl_write', 'The upload could not be completed.', array( 'status' => 500 ) ); }
	if ( $index < $total - 1 ) { return array( 'received' => $index + 1 ); }
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$metadata = wp_read_audio_metadata( $part );
	if ( ! $metadata || empty( $metadata['length'] ) || ! in_array( $metadata['fileformat'] ?? '', 'wav' === $extension ? array( 'wav', 'riff' ) : array( 'mp3' ), true ) ) {
		wp_delete_file( $part );
		return new WP_Error( 'irl_audio', 'This file is not readable audio of the selected format.', array( 'status' => 400 ) );
	}
	if ( ! rename( $part, $dest ) ) { return new WP_Error( 'irl_write', 'Could not finish saving the audio.', array( 'status' => 500 ) ); }
	return array( 'file' => 'media/' . $name, 'title' => sanitize_text_field( $metadata['title'] ?? pathinfo( (string) $req->get_param( 'name' ), PATHINFO_FILENAME ) ) );
}

add_action( 'rest_api_init', function () {
	$permission = function () { return current_user_can( 'manage_options' ); };
	register_rest_route( 'irl/v1', '/catalog', array(
		array( 'methods' => 'GET', 'permission_callback' => $permission, 'callback' => function () { $c = irl_catalog(); return array( 'catalog' => $c, 'revision' => irl_catalog_revision( $c ) ); } ),
		array( 'methods' => 'POST', 'permission_callback' => $permission, 'callback' => 'irl_save_catalog' ),
	) );
	register_rest_route( 'irl/v1', '/catalog-upload', array( 'methods' => 'POST', 'permission_callback' => $permission, 'callback' => 'irl_catalog_upload' ) );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'settings_page_ian-listen' !== $hook ) { return; }
	wp_enqueue_media();
	wp_enqueue_script( 'irl-admin', plugin_dir_url( __FILE__ ) . 'admin.js', array( 'media-editor' ), IRL_VERSION, true );
	wp_enqueue_style( 'irl-admin', plugin_dir_url( __FILE__ ) . 'admin.css', array(), IRL_VERSION );
	wp_localize_script( 'irl-admin', 'IRL_ADMIN', array( 'api' => rest_url( 'irl/v1/' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'media' => irl_media()['url'] . '/', 'player' => plugin_dir_url( __FILE__ ) . 'player/' ) );
} );
