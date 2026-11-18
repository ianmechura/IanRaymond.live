<?php
/** Press kits (EPKs): uploaded PDFs with stable public URLs. The main one replaces the theme's press kit link. */
defined( 'ABSPATH' ) || exit;

const IRL_EPK_THEME_PATH = '/wp-content/themes/ian-raymond/assets/Ian-Raymond-EPK.pdf'; // the theme's bundled press kit (/epk/ fallback)
const IRL_EPK_MAIN       = '_main'; // query value for /epk/ (slugs cannot contain underscores)
const IRL_EPK_TYPES      = array( 'Full press kit', 'One-sheet', 'Venue / booking', 'Radio', 'Festival', 'Media / press' );

function irl_epk_dir() {
	$u = wp_upload_dir( null, false );
	return array( 'path' => trailingslashit( $u['basedir'] ) . 'ian-epk', 'url' => trailingslashit( $u['baseurl'] ) . 'ian-epk' );
}

function irl_epk_max_bytes() {
	return (int) min( 64 * 1024 * 1024, wp_max_upload_size() );
}

/** Stored as array( 'main' => id, 'items' => list of id, name, slug, file, size, uploaded, original ). */
function irl_epks() {
	$data = get_option( 'irl_epks', array() );
	$items = isset( $data['items'] ) && is_array( $data['items'] ) ? array_values( $data['items'] ) : array();
	$main = (string) ( $data['main'] ?? '' );
	return array( 'main' => irl_epk_find( $items, $main ) ? $main : '', 'items' => $items );
}

function irl_epk_find( $items, $id ) {
	foreach ( $items as $item ) {
		if ( '' !== $id && $item['id'] === $id ) { return $item; }
	}
	return null;
}

function irl_epk_url( $slug = '' ) {
	return home_url( '/epk/' . ( '' === $slug ? '' : $slug . '/' ) );
}

function irl_epk_main() {
	$epks = irl_epks();
	return irl_epk_find( $epks['items'], $epks['main'] );
}

/** Lowercase letters, numbers and hyphens; generated from the name when left blank; unique. */
function irl_epk_slug( $name, $slug, $items, $except_id = '' ) {
	$clean = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( '' === trim( (string) $slug ) ? (string) $name : (string) $slug ) ) ), '-' );
	$clean = substr( $clean, 0, 80 );
	if ( '' === $clean ) { return new WP_Error( 'irl_epk_slug', 'Give the press kit a URL name using letters or numbers.' ); }
	foreach ( $items as $item ) {
		if ( $item['id'] !== $except_id && $item['slug'] === $clean ) {
			return new WP_Error( 'irl_epk_slug', 'Another press kit already uses /epk/' . $clean . '/. Choose a different URL name.' );
		}
	}
	return $clean;
}

/** Validate an uploaded PDF and move it into the press kit folder under a unique name. */
function irl_epk_store( $tmp, $original, $slug, $move ) {
	$size = is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
	if ( 'pdf' !== strtolower( pathinfo( (string) $original, PATHINFO_EXTENSION ) ) ) {
		return new WP_Error( 'irl_epk_type', 'Choose a PDF file.' );
	}
	if ( $size < 1 || $size > irl_epk_max_bytes() ) {
		return new WP_Error( 'irl_epk_size', 'Choose a PDF under ' . size_format( irl_epk_max_bytes() ) . '.' );
	}
	if ( '%PDF-' !== (string) file_get_contents( $tmp, false, null, 0, 5 ) ) {
		return new WP_Error( 'irl_epk_type', 'That file is not a readable PDF.' );
	}
	$dir = irl_epk_dir();
	wp_mkdir_p( $dir['path'] );
	if ( ! file_exists( $dir['path'] . '/index.php' ) ) { file_put_contents( $dir['path'] . '/index.php', "<?php\n// Silence.\n" ); }
	$file = $slug . '-' . bin2hex( random_bytes( 6 ) ) . '.pdf';
	if ( ! call_user_func( $move, $tmp, $dir['path'] . '/' . $file ) ) {
		return new WP_Error( 'irl_epk_write', 'Could not save the PDF to the uploads folder.' );
	}
	return array( 'file' => $file, 'size' => $size, 'uploaded' => time(), 'original' => sanitize_file_name( wp_basename( (string) $original ) ) );
}

function irl_epk_put( $epks ) {
	$data = array( 'main' => $epks['main'], 'items' => array_values( $epks['items'] ) );
	if ( ! update_option( 'irl_epks', $data, false ) && get_option( 'irl_epks' ) !== $data ) {
		return new WP_Error( 'irl_epk_save', 'The press kits could not be saved. Please try again.' );
	}
	return true;
}

function irl_epk_remove_file( $file ) {
	$path = irl_epk_dir()['path'] . '/' . wp_basename( (string) $file );
	if ( '' !== (string) $file && is_file( $path ) ) { wp_delete_file( $path ); }
}

function irl_epk_add( $name, $slug, $tmp, $original, $make_main, $move = 'move_uploaded_file' ) {
	$epks = irl_epks();
	$name = sanitize_text_field( (string) $name );
	if ( '' === $name || strlen( $name ) > 120 ) { return new WP_Error( 'irl_epk_name', 'Give the press kit a name or type (up to 120 characters).' ); }
	$slug = irl_epk_slug( $name, $slug, $epks['items'] );
	if ( is_wp_error( $slug ) ) { return $slug; }
	$stored = irl_epk_store( $tmp, $original, $slug, $move );
	if ( is_wp_error( $stored ) ) { return $stored; }
	$item = array_merge( array( 'id' => 'epk-' . bin2hex( random_bytes( 6 ) ), 'name' => $name, 'slug' => $slug ), $stored );
	$epks['items'][] = $item;
	if ( $make_main || '' === $epks['main'] ) { $epks['main'] = $item['id']; }
	$saved = irl_epk_put( $epks );
	if ( is_wp_error( $saved ) ) { irl_epk_remove_file( $stored['file'] ); return $saved; }
	return $item;
}

/** A new version keeps the same name and URL; the previous PDF is deleted. */
function irl_epk_replace( $id, $tmp, $original, $move = 'move_uploaded_file' ) {
	$epks = irl_epks();
	foreach ( $epks['items'] as $i => $item ) {
		if ( $item['id'] !== $id ) { continue; }
		$stored = irl_epk_store( $tmp, $original, $item['slug'], $move );
		if ( is_wp_error( $stored ) ) { return $stored; }
		$epks['items'][$i] = array_merge( $item, $stored );
		$saved = irl_epk_put( $epks );
		if ( is_wp_error( $saved ) ) { irl_epk_remove_file( $stored['file'] ); return $saved; }
		irl_epk_remove_file( $item['file'] );
		return $epks['items'][$i];
	}
	return new WP_Error( 'irl_epk_missing', 'That press kit no longer exists. Reload the page.' );
}

function irl_epk_update( $id, $name, $slug ) {
	$epks = irl_epks();
	$name = sanitize_text_field( (string) $name );
	if ( '' === $name || strlen( $name ) > 120 ) { return new WP_Error( 'irl_epk_name', 'Give the press kit a name or type (up to 120 characters).' ); }
	foreach ( $epks['items'] as $i => $item ) {
		if ( $item['id'] !== $id ) { continue; }
		$clean = irl_epk_slug( $name, '' === trim( (string) $slug ) ? $item['slug'] : $slug, $epks['items'], $id );
		if ( is_wp_error( $clean ) ) { return $clean; }
		$epks['items'][$i]['name'] = $name;
		$epks['items'][$i]['slug'] = $clean;
		$saved = irl_epk_put( $epks );
		return is_wp_error( $saved ) ? $saved : $epks['items'][$i];
	}
	return new WP_Error( 'irl_epk_missing', 'That press kit no longer exists. Reload the page.' );
}

/** An empty id returns the site to the theme's built-in press kit. */
function irl_epk_set_main( $id ) {
	$epks = irl_epks();
	if ( '' !== $id && ! irl_epk_find( $epks['items'], $id ) ) { return new WP_Error( 'irl_epk_missing', 'That press kit no longer exists. Reload the page.' ); }
	$epks['main'] = $id;
	return irl_epk_put( $epks );
}

function irl_epk_delete( $id ) {
	$epks = irl_epks();
	$item = irl_epk_find( $epks['items'], $id );
	if ( ! $item ) { return new WP_Error( 'irl_epk_missing', 'That press kit no longer exists. Reload the page.' ); }
	$epks['items'] = array_filter( $epks['items'], function ( $x ) use ( $id ) { return $x['id'] !== $id; } );
	if ( $epks['main'] === $id ) { $epks['main'] = ''; }
	$saved = irl_epk_put( $epks );
	if ( is_wp_error( $saved ) ) { return $saved; }
	irl_epk_remove_file( $item['file'] );
	return true;
}

// ---- Public URLs: /epk/ (main) and /epk/<slug>/ ---------------------------------------------------
add_action( 'init', function () {
	add_rewrite_rule( '^epk/?$', 'index.php?irl_epk=' . IRL_EPK_MAIN, 'top' );
	add_rewrite_rule( '^epk/([a-z0-9-]+)/?$', 'index.php?irl_epk=$matches[1]', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'irl_epk'; return $vars; } );
add_action( 'template_redirect', function () {
	if ( get_query_var( 'irl_epk' ) ) { irl_epk_serve( get_query_var( 'irl_epk' ) ); exit; }
}, 0 );

if ( ! function_exists( 'irl_epk_header' ) ) {
	function irl_epk_header( $line ) { header( $line ); } // replaceable in tests
}

function irl_epk_serve( $key ) {
	$epks = irl_epks();
	$item = null;
	if ( IRL_EPK_MAIN === $key ) {
		$item = irl_epk_find( $epks['items'], $epks['main'] );
		if ( ! $item ) { wp_redirect( home_url( IRL_EPK_THEME_PATH ), 302 ); return; } // no main chosen: the theme's press kit
	} else {
		foreach ( $epks['items'] as $x ) { if ( $x['slug'] === $key ) { $item = $x; } }
	}
	$path = $item ? irl_epk_dir()['path'] . '/' . wp_basename( $item['file'] ) : '';
	if ( ! $item || ! is_file( $path ) ) {
		status_header( 404 );
		nocache_headers();
		irl_epk_header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'Press kit not found.';
		return;
	}
	// Revalidate every time so a new version shows up immediately at the same URL.
	$etag = '"' . md5( $item['file'] . '-' . $item['size'] ) . '"';
	irl_epk_header( 'Cache-Control: public, max-age=0, must-revalidate' );
	irl_epk_header( 'ETag: ' . $etag );
	irl_epk_header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) $item['uploaded'] ) . ' GMT' );
	if ( trim( (string) ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) ) === $etag ) { status_header( 304 ); return; }
	status_header( 200 );
	irl_epk_header( 'Content-Type: application/pdf' );
	irl_epk_header( 'Content-Length: ' . filesize( $path ) );
	irl_epk_header( 'Content-Disposition: inline; filename="Ian-Raymond-EPK-' . $item['slug'] . '.pdf"' );
	irl_epk_header( 'X-Content-Type-Options: nosniff' );
	if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { readfile( $path ); }
}

/** The theme links to ir_settings['press_pdf'] (falling back to its bundled PDF); supply /epk/ while a main press kit is chosen.
 * Front end only, so saving the theme's own settings never stores this value. The player link is set in irl_album(). */
function irl_epk_theme_settings( $settings ) {
	if ( is_admin() || ! irl_epk_main() ) { return $settings; }
	$settings = is_array( $settings ) ? $settings : array();
	$settings['press_pdf'] = irl_epk_url();
	return $settings;
}
add_filter( 'option_ir_settings', 'irl_epk_theme_settings' );
add_filter( 'default_option_ir_settings', 'irl_epk_theme_settings' );

// ---- Settings → Ian Raymond Press Kits -------------------------------------------------------------
add_action( 'admin_menu', function () {
	add_options_page( 'Ian Raymond Press Kits', 'Ian Raymond Press Kits', 'manage_options', 'ian-epk', 'irl_epk_admin_page' );
} );

add_action( 'admin_post_irl_epk', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'You are not allowed to manage press kits.', 403 ); }
	check_admin_referer( 'irl_epk' );
	$op   = sanitize_key( $_POST['op'] ?? '' );
	$id   = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
	$file = $_FILES['pdf'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated in irl_epk_store()
	$upload_error = function () use ( $file ) {
		if ( ! $file || UPLOAD_ERR_NO_FILE === $file['error'] ) { return new WP_Error( 'irl_epk_file', 'Choose a PDF to upload.' ); }
		if ( in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) { return new WP_Error( 'irl_epk_size', 'Choose a PDF under ' . size_format( irl_epk_max_bytes() ) . '.' ); }
		return UPLOAD_ERR_OK === $file['error'] ? null : new WP_Error( 'irl_epk_file', 'The upload did not finish. Try again.' );
	};
	switch ( $op ) {
		case 'add':
			$result = $upload_error() ?: irl_epk_add( wp_unslash( $_POST['name'] ?? '' ), wp_unslash( $_POST['slug'] ?? '' ), $file['tmp_name'], $file['name'], ! empty( $_POST['main'] ) );
			$done   = 'Press kit added.';
			break;
		case 'replace':
			$result = $upload_error() ?: irl_epk_replace( $id, $file['tmp_name'], $file['name'] );
			$done   = 'New version uploaded. The URL is unchanged.';
			break;
		case 'update':
			$result = irl_epk_update( $id, wp_unslash( $_POST['name'] ?? '' ), wp_unslash( $_POST['slug'] ?? '' ) );
			$done   = 'Press kit updated.';
			break;
		case 'main':
			$result = irl_epk_set_main( $id );
			$done   = '' === $id ? 'The site now links to the theme’s built-in press kit.' : 'Main press kit changed. The site’s press kit links now open it.';
			break;
		case 'delete':
			$result = irl_epk_delete( $id );
			$done   = 'Press kit deleted.';
			break;
		default:
			$result = new WP_Error( 'irl_epk_op', 'Unknown action.' );
	}
	set_transient( 'irl_epk_notice_' . get_current_user_id(), is_wp_error( $result ) ? array( 'error', $result->get_error_message() ) : array( 'success', $done ), 120 );
	wp_safe_redirect( admin_url( 'options-general.php?page=ian-epk' ) );
	exit;
} );

function irl_epk_admin_page() {
	$epks   = irl_epks();
	$dir    = irl_epk_dir();
	$main   = irl_epk_find( $epks['items'], $epks['main'] );
	$max    = irl_epk_max_bytes();
	$notice = get_transient( 'irl_epk_notice_' . get_current_user_id() );
	delete_transient( 'irl_epk_notice_' . get_current_user_id() );
	$hidden = function ( $op, $id = '' ) {
		wp_nonce_field( 'irl_epk' );
		echo '<input type="hidden" name="action" value="irl_epk"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
	};
	$copy = function ( $url, $label ) {
		echo '<span class="irl-epk-url"><input type="text" readonly value="' . esc_attr( $url ) . '" aria-label="' . esc_attr( $label ) . '"><button type="button" class="button irl-epk-copy">Copy URL</button></span>';
	};
	$post = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<div class="wrap irl-epk">
		<h1>Ian Raymond Press Kits</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endif; ?>
		<h2>Main press kit</h2>
		<p>The website’s press kit links (homepage contact section and the music player) open <strong>/epk/</strong>, which shows the main press kit.</p>
		<p><?php $copy( irl_epk_url(), 'Main press kit URL' ); ?></p>
		<p><?php echo $main ? 'Currently: <strong>' . esc_html( $main['name'] ) . '</strong>.' : 'No main press kit is chosen, so the site uses the theme’s built-in PDF.'; ?></p>
		<?php if ( $main ) : ?>
			<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>"><?php $hidden( 'main' ); ?><button class="button-link">Go back to the theme’s built-in press kit</button></form>
		<?php endif; ?>

		<h2>Press kits</h2>
		<?php if ( ! $epks['items'] ) : ?>
			<p>No press kits uploaded yet.</p>
		<?php else : ?>
		<table class="widefat striped irl-epk-table">
			<thead><tr><th>Press kit</th><th>Shareable URL</th><th>Current version</th><th>Actions</th></tr></thead>
			<tbody>
			<?php foreach ( $epks['items'] as $item ) : $is_main = $item['id'] === $epks['main']; ?>
				<tr>
					<td><strong><?php echo esc_html( $item['name'] ); ?></strong><?php echo $is_main ? ' <span class="irl-epk-badge">Main</span>' : ''; ?></td>
					<td><?php $copy( irl_epk_url( $item['slug'] ), $item['name'] . ' URL' ); ?></td>
					<td><a href="<?php echo esc_url( irl_epk_url( $item['slug'] ) ); ?>" target="_blank"><?php echo esc_html( $item['original'] ?: 'View PDF' ); ?></a><br><small><?php echo esc_html( size_format( $item['size'], 1 ) . ' · uploaded ' . wp_date( 'M j, Y g:i a', (int) $item['uploaded'] ) ); ?></small></td>
					<td class="irl-epk-actions">
						<form method="post" enctype="multipart/form-data" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>"><?php $hidden( 'replace', $item['id'] ); ?>
							<input type="file" name="pdf" accept=".pdf,application/pdf" required aria-label="New version of <?php echo esc_attr( $item['name'] ); ?>"><button class="button">Upload new version</button></form>
						<?php if ( ! $is_main ) : ?>
							<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>"><?php $hidden( 'main', $item['id'] ); ?><button class="button">Make main</button></form>
						<?php endif; ?>
						<details><summary>Rename or change URL</summary>
							<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>"><?php $hidden( 'update', $item['id'] ); ?>
								<label>Name or type <input type="text" name="name" required maxlength="120" list="irl-epk-types" value="<?php echo esc_attr( $item['name'] ); ?>"></label>
								<label>URL name <input type="text" name="slug" maxlength="80" pattern="[a-z0-9\-]+" value="<?php echo esc_attr( $item['slug'] ); ?>"></label>
								<small>Changing the URL name breaks links you already sent.</small>
								<button class="button">Save</button></form>
						</details>
						<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="irl-epk-delete" data-name="<?php echo esc_attr( $item['name'] ); ?>" data-main="<?php echo $is_main ? '1' : ''; ?>"><?php $hidden( 'delete', $item['id'] ); ?><button class="button-link irl-epk-remove">Delete</button></form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<h2>Add a press kit</h2>
		<form method="post" enctype="multipart/form-data" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="irl-epk-add"><?php $hidden( 'add' ); ?>
			<label>Name or type <input type="text" name="name" required maxlength="120" list="irl-epk-types" placeholder="e.g. Full press kit"></label>
			<label>URL name <input type="text" name="slug" maxlength="80" pattern="[a-z0-9\-]+" placeholder="Made from the name if blank"></label>
			<label>PDF <input type="file" name="pdf" accept=".pdf,application/pdf" required></label>
			<label class="irl-epk-check"><input type="checkbox" name="main" value="1" <?php checked( ! $main ); ?>> Make this the main press kit</label>
			<p><button class="button button-primary">Upload press kit</button> <small>PDF only, up to <?php echo esc_html( size_format( $max ) ); ?>.</small></p>
		</form>
		<datalist id="irl-epk-types"><?php foreach ( IRL_EPK_TYPES as $t ) { echo '<option value="' . esc_attr( $t ) . '">'; } ?></datalist>
	</div>
	<style>
	.irl-epk-url{display:inline-flex;gap:6px;align-items:center;max-width:100%}.irl-epk-url input{width:340px;max-width:100%}
	.irl-epk-table{max-width:1200px}.irl-epk-table td{vertical-align:top}.irl-epk-actions form{margin:0 0 8px}.irl-epk-actions details form,.irl-epk-add{display:grid;gap:10px;max-width:520px}
	.irl-epk-actions details label,.irl-epk-add label{display:grid;gap:4px;font-weight:600}.irl-epk-add .irl-epk-check{display:block;font-weight:400}
	.irl-epk-badge{background:#2271b1;color:#fff;border-radius:3px;padding:1px 6px;font-size:11px;margin-left:4px}.irl-epk-remove{color:#b32d2e!important}
	@media(max-width:782px){.irl-epk-url{display:flex;flex-wrap:wrap}.irl-epk-url input{width:100%}}
	</style>
	<script>
	(() => {
		const max = <?php echo (int) $max; ?>;
		document.querySelectorAll('.irl-epk-copy').forEach(b => b.addEventListener('click', async () => {
			const input = b.previousElementSibling;
			try { await navigator.clipboard.writeText(input.value); b.textContent = 'Copied'; }
			catch { input.select(); b.textContent = 'Press Ctrl+C'; }
			setTimeout(() => { b.textContent = 'Copy URL'; }, 2000);
		}));
		document.querySelectorAll('.irl-epk form').forEach(f => f.addEventListener('submit', e => {
			const file = f.querySelector('input[type=file]')?.files[0];
			if (file && file.size > max) { e.preventDefault(); alert('That PDF is larger than the ' + Math.floor(max / 1048576) + ' MB upload limit.'); return; }
			if (f.classList.contains('irl-epk-delete')) {
				const extra = f.dataset.main ? ' It is the main press kit, so the site will go back to the theme’s built-in PDF.' : '';
				if (!confirm('Delete “' + f.dataset.name + '”? Its URL will stop working.' + extra)) e.preventDefault();
			}
		}));
	})();
	</script>
	<?php
}
