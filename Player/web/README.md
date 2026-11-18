# Ian Raymond music player on ianraymond.live

Version 1.2 supports albums and singles together on the homepage and `/listen`.

## Manage releases

Open **Settings → Ian Raymond Player** in WordPress.

1. Click **Add album or single**, enter its name, and choose **Album** or **Single**.
2. Click **Choose artwork** to upload or select a cover in the WordPress media library.
3. Click **Upload songs** and select the release’s MP3s (up to 512 MB per song). Uploads use small chunks.
4. Edit song titles and use **Move up / Move down** to arrange songs and releases. **Replace MP3** updates the audio for an existing song without changing its title or position.
5. Click **Save releases**. The public player shows each release’s artwork, type, name, and ordered songs in its own section. One playback bar serves the whole page; Play all, next, previous, shuffle, and repeat span the releases.

Uploads and edits stay unpublished until Save releases. Removing a song or release removes it from the player when saved; audio files remain on disk. Every new upload has a unique filename, so songs with the same filename on different releases cannot overwrite each other. If another session saves first, reload before saving your edits.

Existing Root Pass data is used automatically until the first catalog save. Its song artwork, notes, and NyQuil bonus flow are preserved. The catalog is stored in the WordPress `irl_catalog` option and survives plugin replacement. New covers use the media library; the bundled Root Pass artwork remains available. The USB stays a single-album player with its visible Audio Files folder.

## Build and install

Run `python3 tools/build_web.py` in `Player/`. Upload `release/web/ian-raymond-listen.zip` at **Plugins → Add New → Upload Plugin** and replace the installed version. Existing uploads and saved releases are retained.

For the initial Root Pass setup, upload the files in `release/web/upload/` using **Existing release file updates**. Later, the same section can replace an original MP3 by filename immediately. Use the catalog editor for new songs and releases; uploading album.json is a legacy bootstrap operation and does not overwrite a saved catalog.

The homepage checkbox controls whether the player replaces the theme hero. `/listen` remains available separately. If necessary, clear the site’s page cache after changing releases.

## Verification

- `php tools/tests/catalog_test.php`: migration, save/order/remove, artwork, invalid input, permissions, upload sequencing, and stale edits using a WordPress adapter.
- `python3 tools/catalog_browser_check.py`: grouped playback, artwork fallback, per-release metadata, NyQuil secret, mobile layout, and admin editing/upload staging using an isolated browser and REST fixtures. Start the browser as described in `tools/TESTING.md` first.
- Verify the installed plugin on the real WordPress admin and public pages after deployment.

## Individual song pages (1.3)

Every published catalog song has a page such as `/IanRaymond/Music/RootPass/Josephine/`, with its own audio player, artwork, notes, canonical URL, social metadata, and MusicRecording structured data. Song titles in the main player open these pages; the play arrows and transport controls continue playback. Next song follows the catalog order and wraps to the beginning. Copy streaming link copies the song page URL, with a selectable field if clipboard access is unavailable.

Pages offer MP3 downloads and WAV downloads when a master has been uploaded. Use **Upload WAV** beside a song in the catalog editor, then **Save releases**. Replacing its MP3 clears the explicitly attached WAV so an old master is not offered for a new mix. The build also stages Root Pass WAV masters in `release/web/upload/`; upload these using **Existing release file updates** with their original filenames. Missing masters show “WAV coming soon” instead of a broken download. WAVs are separate uploads, not bundled into the plugin ZIP.

Installing version 1.3 refreshes rewrite rules automatically. Unknown song URLs return 404. Release and song URL slugs are separate from their display names in the catalog editor. Existing URL spelling is retained; new slugs are generated on first save unless you supply one. Renaming or reordering songs and releases leaves their slugs unchanged. Changing a slug deliberately changes the URL (there is no redirect from the old address); changing a release slug affects all its songs. Slugs accept letters, numbers, hyphens and underscores. Release slugs must be unique; song slugs must be unique within their release, ignoring case. Duplicate display names are allowed. The hidden bonus remains part of the existing unlock flow.

Verification: `php tools/tests/song_test.php`, `php tools/tests/catalog_test.php`, and `python3 tools/song_browser_check.py` (with the isolated browser running as described in `tools/TESTING.md`).

## Bundled update 1.3.1

Includes song pages, independent release/song URL slugs, and the restored `irl_catalog_saved` notification hook. Successful catalog changes pass the saved and previous catalogs to Notifications, which creates approval-required announcement drafts only for newly added release IDs. Renaming, reordering, or changing slugs does not announce existing releases. Failed and unchanged saves do not emit the hook. Installing the plugin alone does not announce existing music.

## Deploy from a script

The deployment script requires local OpenSSH (`ssh` and `scp`), SSH access to the host, and `wp` (WP-CLI) on the server's PATH. Configure any nonstandard port or key in your SSH config. Build once after bundling your changes:

```sh
python3 Player/tools/build_web.py
python3 Player/tools/deploy_web.py --host YOUR_SSH_ALIAS --wp-path /absolute/path/to/wordpress
```

The second command previews the deployment without connecting. Add `--deploy` to upload and install that same ZIP. It backs up the installed Player files under the remote user's `~/.local/state/ian-player-backups/`, replaces and activates the plugin, checks its version, and refreshes routes. It does not upload audio or change the release catalog. Backups contain plugin files, not the database or uploaded music. If installation fails, the script exits with an error; rollback is manual using the saved archive.

Verify `/listen/` and a song page afterward, and purge your host/CDN page cache if needed. Hosting details and live execution are still unverified; the script is ready to configure once SSH access and the WordPress path are known.

## Press kits (EPKs, 1.4)

Open **Settings → Ian Raymond Press Kits**. Upload a PDF with a name or type (Full press kit, One-sheet, Venue / booking, Radio, …). Each press kit gets a shareable URL such as `/epk/venue/`; **Copy URL** copies it. **Upload new version** replaces the PDF and keeps the same URL, so links you already sent open the new version (the previous PDF is deleted). **Rename or change URL** edits the name; changing the URL name breaks links already sent. **Delete** removes the press kit and its PDF.

**Make main** chooses the press kit at `/epk/`. While a main press kit is chosen, the theme's press kit links (its `press_pdf` setting, used by the homepage hero and contact section) and the player's "Press kit · online" button point to `/epk/`. With no main press kit (none uploaded, main deleted, or "Go back to the theme's built-in press kit"), the site is unchanged and `/epk/` redirects to the theme's `Ian-Raymond-EPK.pdf`. PDFs are stored in `wp-content/uploads/ian-epk/`, are limited to the server's upload size (at most 64 MB), and are served with revalidation so new versions show up immediately.

Verification: `php tools/tests/epk_test.php`.
