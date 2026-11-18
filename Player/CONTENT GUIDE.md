# Updating your Ian Raymond player

The player contains the 17 v10 MP3 masters (NyQuil is always last). The audio is copied without changes. Everything it needs for listening is in this folder; website and social links are optional online destinations.

## Replace a mix

1. Close the player tab.
2. Copy the replacement MP3 into `music/`, keeping the existing filename. For example: `music/01 - Josephine.mp3`.
3. Open `PlayIanRaymond.html` again, or refresh after copying has finished.

No HTML, CSS or player JavaScript changes are needed. Track duration is read from the audio file. The browser does not keep a permanent music cache. Avoid copying over a file while it is playing.

To change all eight mixes, replace their files in `music/` the same way. Update the `edition` value in `content/album.js` so the player identifies the new edition. The queued extra 5% vocal increase has **not** been applied to these v6 files.

## The content index

Open `content/album.js` in a text editor. It assigns one data object to `window.IAN_ALBUM`. Keep the opening assignment and ending semicolon. Values inside the object use JSON syntax: double-quoted text, commas between entries, and no comma after the last entry. Represent line breaks inside text as `\n`, and quotes inside text as `\"`.

Paths are relative to `PlayIanRaymond.html`, regardless of where the index is stored. Use forward slashes. Example: `content/artwork/cover.jpg`. Keep assets inside the player folder. The player does not scan directories or fetch the index from a server.

Optional fields use an empty string `""` or empty list `[]` when absent. Empty sections disappear. Content is displayed as plain text, so HTML tags are unnecessary and will not execute.

## Add, remove or reorder songs

Copy the audio into `music/`, then edit the `tracks` list in `content/album.js`. Its order is the playback order. Each song uses:

```json
{
  "id": "a-stable-song-id",
  "title": "Song title",
  "file": "music/09 - Song title.mp3",
  "artwork": "",
  "story": "",
  "lyrics": "",
  "credits": ""
}
```

Keep each `id` unique and stable when reordering or replacing a mix. Remove the entry to remove a song from the player. Unreferenced files do not appear automatically.

Also update `music/Root Pass.m3u`: one filename per line after `#EXTM3U`, in album order. This playlist is for other music applications. The HTML player uses the content index.

If Python 3 is available, `python3 tools/validate_package.py --write-playlist` checks the index and updates the playlist for you. It does not process or change audio. This optional publishing tool is not needed for listening or for replacing an existing file.

## Artwork and song notes

- Set the top-level `artwork` to an album cover path.
- Set a song’s `artwork` for a different image while it is selected.
- Missing song artwork falls back to the album artwork, then the styled album title.
- Add text to a song’s `story`, `lyrics` or `credits`. These appear beneath the track list for the selected song. Missing sections stay hidden.
- Replace an existing image by copying a new file to its referenced filename.

## Welcome text or video

Edit `welcome.text` and/or `welcome.video`. Put local videos in `content/video/`. `welcome.poster` is an optional local image. A broadly supported MP4 is the recommended starting format; verify your particular file in the browsers you expect listeners to use.

A welcome video is never autoplayed. Starting it pauses the music. Afterward, click Play to resume the music. If it cannot load, the player explains that briefly and keeps the music controls available.

## Booklet

For a PDF, set `booklet.file`, for example `content/documents/Album-booklet.pdf`. A local PDF takes precedence over assembled sections.

Alternatively, leave `booklet.file` empty and supply `booklet.intro` and/or `booklet.sections`:

```json
{
  "heading": "About the album",
  "text": "Your own album notes here.",
  "image": "content/artwork/photo.jpg",
  "caption": "Your photo caption."
}
```

Each section is an entry in the `sections` list. Omit empty fields. The built-in booklet view supports printing or saving through the browser’s print dialog. Only include your supplied or confirmed content.

## EPK and contact links

The initial release includes the EPK and public contact links already published on `ianraymond.live`.

- `epk.file`: local PDF. Replace the file at the same path to update it.
- `epk.url`: online EPK address. Empty hides the online button.
- `socialLinks`: entries with a `label` and an `http://` or `https://` URL.
- `booking.email`: public booking email; `booking.label` is its displayed text.
- `website.url` and `website.label`: public website address and display name.

PDF previews depend on the browser’s PDF support. The document window includes a separate-tab link and a fallback message. Validate local document paths before distributing a copy.

## Website QR code

The bundled SVG opens `https://ianraymond.live/`. It works without a connection to display or scan, but visiting the website requires internet.

If the website address changes, replace the QR image and update `website.qrDestination` to the exact destination. The player only displays the QR when that destination matches `website.url`. Set `website.qr` empty to hide it. This prevents an old QR from pointing listeners to a different address than the visible link.

## Prepare a USB copy

Run `python3 tools/build_usb.py /media/ian/<drive>`. The drive's top level then shows only **PlayIanRaymond.html**; everything else goes in the hidden `.assets` folder (dot name for macOS/Linux, FAT hidden attribute for Windows). The script rewrites the page's paths for that layout; the development folder is unchanged. Windows' own `System Volume Information` folder is already hidden on Windows and macOS.

### Older manual method

Keep these together:

- `PlayIanRaymond.html`
- `assets/`
- `content/`
- `music/`
- `START HERE.txt`
- `CONTENT GUIDE.md` and `README.md`

No installation, account or server is required. The USB folder may need to be opened manually. On a computer that opens HTML in an editor, right-click the HTML file and choose a web browser.

The release ZIP in `release/` contains this listening package without development tools or test files. Extract it before opening the HTML file. To prepare a fresh ZIP after updates, run `python3 tools/validate_package.py --write-playlist --package` from this folder. Copy the resulting package onto the USB. Already-distributed drives are independent copies and will not update themselves.

## Release boundaries

V1 supports manual copying of local content. Listener-initiated online updates are recorded for V2 and are not implemented. Browser cache storage is not used as a substitute for files on the USB.

## Secret song (NyQuil easter egg)

Ten seconds into the track whose `id` is `"nyquil"`, a pulsing purple bottle with a "Click here" caption appears above the player bar. It opens a note from Ian. **Add** appends the secret song to the bottom of the list for this session only; a reload starts over. Once added, it behaves like every other song. Its row uses negative colours. While it plays, the whole player goes psychedelic: a morphing fractal, cycling colours, wobbling letters, words that change on the beat, and floating night-time medicine imagery. Visitors whose system asks for reduced motion get calm negative colours instead, and nothing strobes.

Settings live in the `secret` block of `content/album.js`:

- `revealDuringTrack`: the trigger track's `id` (`"nyquil"`). NyQuil must stay in `tracks` with that id.
- `revealAtSeconds`: when the bottle appears (10).
- `psychedelic.bpm`: the song's tempo (140). The pulses and word changes follow it.
- `caption`: the text under the bottle.
- `track`: the secret song. Its file is `content/extras/Naked Billy B-Side Remix.mp3`. It is kept out of `music/` and the playlist on purpose.
- `note`: the title, paragraphs (a paragraph can have a bold `lead`) and button labels.

To replace the secret song, copy a new MP3 over the same file name. Leave `track.file` empty to switch the feature off.
