<?php
/**
 * Plugin Name: Ian Raymond — Music Player
 * Description: Albums and singles at /listen and on the homepage. Manage releases under Settings → Ian Raymond Player and press kits under Settings → Ian Raymond Press Kits.
 * Version: 1.4.5
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Ian Raymond
 */
defined( 'ABSPATH' ) || exit;

const IRL_VERSION = '1.4.5';
const IRL_ROUTE   = 'listen';
const IRL_PAGE_TITLE = 'Original Music by Ian Raymond'; // browser title for /listen and the homepage player
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/songs.php';
require_once __DIR__ . '/epk.php';

/** Songs and album.json live in wp-content/uploads/ian-listen/ (uploaded on the settings page). */
function irl_media() {
	$u = wp_upload_dir( null, false );
	return array(
		'path' => trailingslashit( $u['basedir'] ) . 'ian-listen',
		'url'  => trailingslashit( $u['baseurl'] ) . 'ian-listen',
	);
}

/** Album data: an uploaded album.json replaces the one bundled with the plugin. */
function irl_base_album() {
	$m    = irl_media();
	$file = file_exists( $m['path'] . '/album.json' ) ? $m['path'] . '/album.json' : __DIR__ . '/player/album.json';
	$data = json_decode( (string) file_get_contents( $file ), true );
	return is_array( $data ) && isset( $data['tracks'] ) && is_array( $data['tracks'] ) ? $data : null;
}

/** Every media file the album refers to (media/<name>), e.g. "Josephine.mp3". */
function irl_expected_files( $album ) {
	$names = array();
	if ( $album ) {
		array_walk_recursive(
			$album,
			function ( $value ) use ( &$names ) {
				if ( is_string( $value ) && 0 === strpos( $value, 'media/' ) ) {
					$names[] = substr( $value, 6 );
				}
			}
		);
	}
	return array_values( array_unique( $names ) );
}

// ---- /listen route ------------------------------------------------------------------------------
function irl_rewrite() {
	add_rewrite_rule( '^IanRaymond/Music/([^/]+)/([^/]+)/?$', 'index.php?irl_song=$matches[1]/$matches[2]', 'top' );
	add_rewrite_rule( '^' . IRL_ROUTE . '/?$', 'index.php?irl_listen=1', 'top' );
}
add_action( 'init', 'irl_rewrite' );
add_action( 'init', function () {
	if ( get_option( 'irl_routes_version' ) !== IRL_VERSION ) { flush_rewrite_rules( false ); update_option( 'irl_routes_version', IRL_VERSION, false ); }
}, 20 );
add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'irl_listen';
		$vars[] = 'irl_song';
		return $vars;
	}
);
register_activation_hook(
	__FILE__,
	function () {
		irl_rewrite();
		flush_rewrite_rules();
		wp_mkdir_p( irl_media()['path'] );
	}
);
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action(
	'template_redirect',
	function () {
		if ( get_query_var( 'irl_song' ) ) { irl_song_render( get_query_var( 'irl_song' ) ); exit; }
		if ( ! get_query_var( 'irl_listen' ) ) {
			return;
		}
		irl_render();
		exit;
	}
);

/** Fill a player template: asset URLs, cache stamp and the album data (song URLs point at the uploads folder). */
function irl_fill( $html ) {
	$album = irl_album();
	if ( ! $album || ! $html ) {
		return null;
	}
	$album['tracks'] = irl_song_entries( $album );
	$album['pageTitle'] = IRL_PAGE_TITLE;
	$m      = irl_media();
	$player = plugin_dir_url( __FILE__ ) . 'player/';
	$latest = 0;
	foreach ( (array) glob( $m['path'] . '/*' ) as $f ) {
		$latest = max( $latest, (int) filemtime( $f ) );
	}
	// media/… → uploaded files; content/… → files bundled with the plugin.
	$map = function ( $p ) use ( $m, $player ) {
		$enc = function ( $s ) {
			return implode( '/', array_map( 'rawurlencode', explode( '/', $s ) ) );
		};
		if ( 0 === strpos( $p, 'media/' ) ) {
			return $m['url'] . '/' . $enc( substr( $p, 6 ) );
		}
		if ( 0 === strpos( $p, 'content/' ) ) {
			return $player . $enc( $p );
		}
		return $p;
	};
	array_walk_recursive(
		$album,
		function ( &$value, $key ) use ( $map ) {
			if ( is_string( $value ) && in_array( $key, array( 'file', 'artwork', 'releaseArtwork', 'qr', 'poster', 'video', 'image' ), true ) && '' !== $value ) {
				$value = $map( $value );
			}
		}
	);
	$json = wp_json_encode( $album, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
	return str_replace(
		array( '{{ASSETS}}', '{{BUILD}}', '{{ALBUM_JSON}}', '{{PAGE_TITLE}}' ),
		array( esc_url( $player . 'assets/' ), esc_js( IRL_VERSION . '-' . $latest . '-' . substr( irl_catalog_revision( irl_catalog() ), 0, 12 ) ), $json, esc_html( IRL_PAGE_TITLE ) ),
		$html
	);
}

function irl_render() {
	$html = irl_fill( (string) file_get_contents( __DIR__ . '/player/index.html' ) );
	if ( ! $html ) {
		status_header( 503 );
		echo 'The player is being updated. Please try again shortly.';
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built template; album JSON is hex-escaped.
}

// ---- Homepage mode: the player replaces the theme's hero section; the rest of the theme page stays ----
function irl_home_enabled() {
	return is_front_page() && ( '1' === get_option( 'irl_home', '0' ) || isset( $_GET['irl_home_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- read-only preview
}
add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'irl_listen' ) && irl_home_enabled() ) {
			ob_start( 'irl_home_filter' );
		}
	},
	20
);
/** Swap the theme's <section class="hero …"> for the player. If anything is missing, return the page unchanged. */
function irl_home_filter( $page ) {
	$head = irl_fill( (string) @file_get_contents( __DIR__ . '/player/home-head.html' ) );
	$body = irl_fill( (string) @file_get_contents( __DIR__ . '/player/home-fragment.html' ) );
	if ( ! $head || ! $body || false === stripos( $page, '</head>' ) ) {
		return $page;
	}
	$count = 0;
	$page  = preg_replace_callback(
		'#<section class="hero[^"]*"[^>]*>.*?</section>#s',
		function () use ( $body ) {
			return $body;
		},
		$page,
		1,
		$count
	);
	if ( 1 !== $count ) {
		return $page; // theme layout changed: leave the homepage exactly as the theme drew it
	}
	return str_ireplace( '</head>', $head . '</head>', $page );
}

// ---- Settings → Root Pass Player: upload songs --------------------------------------------------
add_action(
	'admin_init',
	function () {
		register_setting( 'irl', 'irl_home', array( 'type' => 'string', 'sanitize_callback' => function ( $v ) { return '1' === $v ? '1' : '0'; }, 'default' => '0' ) );
	}
);
add_action(
	'admin_menu',
	function () {
		add_options_page( 'Ian Raymond Player', 'Ian Raymond Player', 'manage_options', 'ian-listen', 'irl_admin_page' );
	}
);

function irl_admin_page() {
	$m     = irl_media();
	$album = irl_album();
	$names = array_merge( array( 'album.json' ), irl_expected_files( $album ) );
	?>
	<div class="wrap">
		<h1>Ian Raymond Player</h1>
		<p>The player is at <a href="<?php echo esc_url( home_url( '/' . IRL_ROUTE ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/' . IRL_ROUTE ) ); ?></a>.</p>
		<h2>Homepage</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'irl' ); ?>
			<label><input type="checkbox" name="irl_home" value="1" <?php checked( get_option( 'irl_home', '0' ), '1' ); ?>> Show the player at the top of the homepage (replaces the hero section; the story, videos, booking, updates and contact sections stay below)</label>
			<p><a href="<?php echo esc_url( add_query_arg( 'irl_home_preview', '1', home_url( '/' ) ) ); ?>" target="_blank">Preview the homepage with the player</a> (works before you turn it on) · <?php submit_button( 'Save', 'secondary', 'submit', false ); ?></p>
		</form>
		<h2>Albums &amp; singles</h2>
		<p>Create a release, choose its artwork, and upload its MP3s. Arrange releases and songs in the order you want listeners to see them.</p>
		<p id="irl-catalog-status" role="status" aria-live="polite">Loading releases…</p>
		<form id="irl-release-form"><fieldset id="irl-editor" disabled>
			<div id="irl-releases"></div>
			<div class="irl-catalog-toolbar"><button type="button" class="button" id="irl-add-release">Add album or single</button><button type="submit" class="button button-primary">Save releases</button></div>
		</fieldset></form>
		<details><summary><strong>Existing release file updates</strong></summary>
		<p>Replace an existing file by uploading an MP3 with the same filename. This updates its audio immediately. To add releases or songs, use the editor above.</p>
		<h2>Songs</h2>
		<p>Choose or drop the files from the <strong>upload</strong> folder made by <code>build_web.py</code>. A file with the same name replaces the old one, so to update a song just upload its new MP3 (and <code>album.json</code> when songs are added, removed or reordered). Large files are sent in small pieces.</p>
		<p><input type="file" id="irl-files" multiple accept=".mp3,.wav,.json"> <button class="button button-primary" id="irl-go">Upload</button></p>
		<pre id="irl-log" style="background:#fff;padding:8px;max-height:260px;overflow:auto"></pre>
		<h2>Files on the site</h2>
		<table class="widefat striped" style="max-width:760px"><thead><tr><th>File</th><th>Size</th><th>Uploaded</th></tr></thead><tbody>
		<?php
		foreach ( $names as $n ) {
			$f = $m['path'] . '/' . $n;
			echo '<tr><td>' . esc_html( $n ) . '</td>';
			if ( file_exists( $f ) ) {
				echo '<td>' . esc_html( size_format( filesize( $f ), 1 ) ) . '</td><td>' . esc_html( wp_date( 'M j, Y g:i a', filemtime( $f ) ) ) . '</td></tr>';
			} else {
				echo '<td colspan="2"><strong style="color:#b32d2e">missing</strong></td></tr>';
			}
		}
		?>
		</tbody></table>
		</details>
	</div>
	<script>
	(() => {
		const api = <?php echo wp_json_encode( esc_url_raw( rest_url( 'irl/v1/upload' ) ) ); ?>, nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
		const log = t => { document.getElementById('irl-log').textContent += t + '\n'; };
		const CHUNK = 1024 * 1024;
		document.getElementById('irl-go').addEventListener('click', async () => {
			// album.json first: it defines which song files are accepted.
			const files = [...document.getElementById('irl-files').files].sort((a, b) => (b.name === 'album.json') - (a.name === 'album.json'));
			for (const f of files) {
				const total = Math.max(1, Math.ceil(f.size / CHUNK));
				try {
					for (let i = 0; i < total; i++) {
						const body = new FormData();
						body.append('name', f.name); body.append('index', i); body.append('total', total);
						body.append('chunk', f.slice(i * CHUNK, (i + 1) * CHUNK), f.name);
						const r = await fetch(api, { method: 'POST', headers: { 'X-WP-Nonce': nonce }, body, credentials: 'same-origin' });
						const j = await r.json();
						if (!r.ok) throw new Error(j.message || r.status);
					}
					log('✓ ' + f.name);
				} catch (e) { log('✗ ' + f.name + ': ' + e.message); }
			}
			log('Done. Reload this page to see the file list.');
		});
	})();
	</script>
	<?php
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'irl/v1',
			'/upload',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => 'irl_upload',
			)
		);
	}
);

function irl_upload( WP_REST_Request $req ) {
	$name  = wp_basename( (string) $req->get_param( 'name' ) );
	$index = (int) $req->get_param( 'index' );
	$total = (int) $req->get_param( 'total' );
	$files = $req->get_file_params();
	if ( empty( $files['chunk']['tmp_name'] ) || $total < 1 || $index < 0 || $index >= $total ) {
		return new WP_Error( 'irl_bad', 'Upload incomplete.', array( 'status' => 400 ) );
	}
	$expected = irl_expected_files( irl_album() );
	$allowed = array_merge( array( 'album.json' ), $expected, array_map( function ( $file ) { return preg_replace( '/\.mp3$/i', '.wav', $file ); }, $expected ) );
	if ( ! in_array( $name, $allowed, true ) || ! preg_match( '/\.(mp3|wav|json)$/i', $name ) ) {
		return new WP_Error( 'irl_name', 'Not a file this album uses (upload album.json first when adding songs).', array( 'status' => 400 ) );
	}
	$m = irl_media();
	wp_mkdir_p( $m['path'] );
	$part  = $m['path'] . '/.' . $name . '.part';
	$chunk = file_get_contents( $files['chunk']['tmp_name'] );
	if ( false === file_put_contents( $part, $chunk, 0 === $index ? 0 : FILE_APPEND ) ) {
		return new WP_Error( 'irl_write', 'Could not write to the uploads folder.', array( 'status' => 500 ) );
	}
	if ( $index < $total - 1 ) {
		return array( 'received' => $index + 1 );
	}
	if ( 'album.json' === $name ) {
		$data = json_decode( (string) file_get_contents( $part ), true );
		if ( ! is_array( $data ) || empty( $data['tracks'] ) ) {
			wp_delete_file( $part );
			return new WP_Error( 'irl_json', 'album.json is not valid album data.', array( 'status' => 400 ) );
		}
	}
	rename( $part, $m['path'] . '/' . $name );
	return array( 'saved' => $name );
}
