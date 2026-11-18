# Ian Raymond — Root Pass player

Open **PlayIanRaymond.html**, then click **Play album** or choose a song. Includes the 11-song Root Pass album, with NyQuil last and a hidden bonus track. The album cover appears on opening; selecting or playing a song shows its artwork.

The player uses the charcoal/gold palette and locally bundled Oswald and IBM Plex Mono fonts from Ian’s website. Lyrics, song stories, a welcome message/video, artwork and booklet sections appear when supplied. The published press kit, public social links, booking address and website QR code are included.

For content changes, see **CONTENT GUIDE.md**. Replacing a mix under the same filename only requires copying it into `music/` and reopening the player. The separate content index controls titles, track order, text, links and optional material.

The player runs directly from local files, with no installation, account, server, analytics, autoplay on opening or internet dependency for local listening. External artist links open separately and require internet. Online content updates are deferred to V2.

**Compatibility:** intended for modern desktop browsers on Windows, macOS and Linux. Actual checks and remaining platform gaps are recorded in `review/VALIDATION.md` in the development folder. A successful Linux browser check does not establish Windows or macOS results. Use the MP3 folder or M3U playlist if a browser cannot run the player.

**Package contents:** HTML, local application assets/fonts, content index and documents, MP3 files and playlist. `tools/`, `review/` and the feature review document are development materials and are excluded from release ZIPs.

Fonts retain their SIL Open Font License files in `assets/`. Website material is Ian Raymond’s published content. Source URLs and audio identity checks are recorded in the development folder.

## Website version

The same player is published at **ianraymond.live/listen** as a small WordPress plugin: `python3 tools/build_web.py`, then follow [web/README.md](web/README.md).

## USB handoff

Run `python3 tools/build_usb.py /path/to/usb`. The USB root contains `PlayIanRaymond.html` and a visible `Audio Files/` folder with the 11 album MP3s, bonus MP3, and Root Pass playlist. The player references those same audio files directly; supporting code and artwork stay in hidden `.assets/`. DJs can copy or import the MP3s without opening the player.
