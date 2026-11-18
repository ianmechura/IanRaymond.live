# Ian Raymond USB Album Player — Features for Review

Status: V1 implemented in `PlayIanRaymond.html`. See `review/VALIDATION.md` for completed checks and platform limitations; V2 remains deferred.

Release scope: the features below define V1 except the explicitly deferred V2 online content updates.

The player will make the USB album easy to hear and personal to explore. Listeners open `PlayIanRaymond.html` in their browser, click Play, and can select any available song. Everything created for this project will live in `Player/`, which will contain the package to copy to the USB drive.

Confirmed starting content: **Root Pass (2026-09-24 v6 - Josephine 5)**, using its eight delivered 320 kbps MP3 files. Use these masters as delivered; the queued vocal increase belongs to a future album revision.

## Core listening experience

| Feature | Planned behavior |
|---|---|
| Open and listen | Open the HTML file directly from the USB drive, with no installation, account, or local server required. Playback begins when the listener clicks Play or a song. |
| Album track list | Show songs in album order, highlight the current song, and allow direct track selection. Show durations when available. |
| Playback controls | Play/pause, previous/next, seek bar, elapsed/total time, volume and mute. Disable seeking until the track is ready. |
| Continuous playback | Advance through the album automatically after the initial Play action; stop after the last song unless repeat is enabled. |
| Shuffle and repeat | Shuffle, repeat album, and repeat current song, with clearly visible states. |
| Now playing | Show the song title, artist and available artwork. Keep controls accessible while browsing supporting content. |
| Original album sound | Play the supplied mastered files without additional EQ, normalization or effects. Player development will not remix the album. |
| Direct access to music | Include an accessible music folder and a playlist for listeners who prefer their own music application. |

## V1 — Update local content by copying files

Keep content separate from the player code so Ian can replace it at any time without rebuilding the player. Use this package layout:

```text
Player/
  PlayIanRaymond.html
  music/                 Album MP3 files and playlist
  content/
    album.js             Content index: edition, track order, titles, text and file references
    artwork/             Album and song images
    documents/           EPK and booklet files
    video/               Optional introduction video
```

| Update | What Ian copies or changes |
|---|---|
| Replace a song with a new mix | Copy the new MP3 into `music/` using the existing filename, such as `01 - Josephine.mp3`. No player-code or index change is needed. Read duration from the replacement audio. |
| Replace the album with a later revision | Copy the replacement MP3 files into `music/`. If the track names and order stay the same, their existing references continue to work. Update the edition label in `content/album.js` to identify the new release. |
| Replace existing artwork, EPK, booklet or video | Copy the new file over the referenced file in its content folder, retaining its filename. |
| Add or remove songs, rename files, or change track order | Copy the files and update the small content index; update the supplied music playlist to match. Player code remains unchanged. |
| Add optional material for the first time | Copy the files into the appropriate content folder and add their references to the content index. The corresponding sections become visible when valid content is supplied. |
| Update lyrics, stories, credits, welcome text or links | Edit or copy in a replacement content index. Missing entries continue to hide gracefully. |
| Distribute a complete content refresh | Supply a replacement `music/` and `content/` folder set with a matching index and playlist. The HTML player and its application files remain reusable. |

For updates, stop playback and close the player tab, finish copying the files, then reopen `PlayIanRaymond.html`. Also support refreshing the page after copying is complete. Do not require restarting the whole browser. Load the content index anew on opening, and avoid persistent caching that would conceal replaced local files.

The content index is a separate, documented data file loaded directly by the local HTML player. New filenames are explicitly listed there; the player does not depend on automatically scanning a folder. Provide a filled-in index and simple update instructions with the initial v6 package. Keep file references relative to the package and identify tracks independently of their display order.

This V1 workflow is manual file copying by Ian or a USB owner. The player itself does not write to the USB. Website-delivered updates remain the separate V2 feature below.

## Personal content and artist links

These features are supported, but only appear when their content is supplied. Missing content never prevents listening.

| Feature | When content is supplied | When content is absent |
|---|---|---|
| Song stories | Display Ian’s note about the selected song. | Hide the story section for that song. |
| Lyrics | Display readable lyrics for the selected song. | Hide the lyrics section for that song. |
| Credits | Show available performance, writing and production credits. | Show only supplied fields; hide the section if empty. |
| Personal welcome | Show a welcome message and an optional, click-to-play introduction video. Pause music when the introduction video plays. | Show text alone if only text is supplied; omit the welcome section if neither is available. |
| Artwork | Use song artwork when supplied, otherwise album artwork. | Use a simple styled artist/title treatment if neither is available. |
| Digital booklet | Offer a local booklet containing supplied photos, artist story and album notes, or link to a supplied PDF. | Hide the booklet link if no booklet is included. Omit missing sections from a booklet assembled from supplied content. |
| EPK | Offer an offline EPK when included, plus an online EPK link if provided. | Show whichever version is available; hide the feature if neither is supplied. |
| YouTube and social links | Show supplied artist links, clearly marked as requiring internet. Open them separately so the player remains available. | Hide individual missing links and omit the whole group if empty. |
| Booking/contact | Show supplied public booking or contact information. | Hide the contact section if nothing is supplied. |
| Website and phone QR code | Link to Ian’s website and include a locally stored QR code for opening that address on a phone. Clearly label the destination. | Hide the link and QR code if no destination is configured. |

Song stories, lyrics, credits and biographical details will come from supplied or confirmed material. Missing content will not be filled with invented information or visible “coming soon” placeholders.

## Handling unavailable content

- Hide empty sections, tabs and navigation links, and let the remaining layout close the space naturally.
- Treat blank text and empty lists as absent. A song can have lyrics without a story, or a story without lyrics.
- Check bundled content and file paths when preparing the USB package. Avoid shipping buttons that point to missing local files.
- If an image fails to load, fall back to album artwork or the artist/title treatment.
- If a music file is missing or cannot play, keep the player usable, mark that track unavailable with a brief message, and allow selection of another track. During continuous playback, skip unavailable tracks with a visible notice; stop with a clear message if none can play. Avoid repeated skip loops.
- If optional media fails to open, keep music playback and the rest of the interface usable, with a brief message for the requested item.
- Online destinations may be unavailable even when a link is configured. Label them as online resources without making network access a requirement for listening or claiming to verify their availability at runtime.

## Appearance and accessibility

- Match the inspected `ianraymond.live` design: charcoal background, warm gold, Oswald headings and IBM Plex Mono labels, with fonts bundled locally.
- Keep Play and the track list prominent, with optional material available without cluttering the listening controls.
- Use a responsive layout for different window sizes, readable text, clear contrast and large click targets.
- Support keyboard navigation, visible focus, accessible control labels and playback-state announcements.
- Keep all required fonts, images, scripts, styles and audio in the package so the core experience works offline.

## Delivery and review checks

- Target desktop browsers on Windows, macOS and Linux. Test opening the actual HTML file directly from disk; record which browser/OS combinations were actually tested and any remaining gaps.
- Do not rely on the USB folder opening automatically. Include a short `START HERE.txt` explaining how to open the player and click Play.
- Use relative file paths so moving the package or changing the USB drive letter does not break it.
- Keep album data and optional content in a documented configuration so future tracks, text and links can be updated without redesigning the interface. The configuration must work when opened directly from disk.
- Keep the package independent of development tools, internet-hosted libraries and streaming services.
- Review the player with music only, with partial optional content, and with all available content. Check missing images, missing audio, offline use, keyboard controls and continuous playback.
- Verify that replacing an MP3 under the same filename uses the new recording and duration after reopening. Check artwork/document replacement, adding and removing optional content, and changing the track list through the content index without changing player code.
- Document how to update the content, select the album edition for release, and copy the complete package onto a USB drive.

## V2 — Optional online content updates (deferred)

The USB ships with a complete, independently playable edition of the album. In V2, listeners can choose **Check for updates** to discover additional or revised content published on Ian’s website and make it available within the player. This feature is recorded for future implementation and is outside V1 scope.

| Feature | Planned V2 behavior |
|---|---|
| Listener-initiated check | Contact a configured website update location only when the listener chooses to check. Show whether updates are available, no updates were found, or the check could not complete. |
| Published content releases | Ian can upload a content package and release listing to his website describing the version, changes and available resources. Define the publishing format during V2 implementation. |
| Updated content in the player | Retrieve available online resources and expose new or revised songs, artwork, lyrics, stories, EPK material and other supported content through the player. Use stable content identifiers to distinguish additions from replacements and avoid duplicate entries. |
| Clear edition information | Distinguish the USB edition from available online updates. Show what changed and let the listener choose to load the update without interrupting a playing song. |
| USB remains intact | Keep the shipped files unchanged. Online resources supplement the listening experience without writing updates into the USB folder. |
| Connection or resource failure | Keep the original USB content playable. Fall back to a bundled version when an updated resource cannot load; show a brief unavailable message for online-only content. A failed check must not be reported as “up to date.” |
| Optional browser caching | Evaluate caching downloaded resources for later use during V2. Clearly distinguish content available offline from content requiring internet. Do not promise permanent offline storage: browser-managed data may be cleared or unavailable. |
| Opening an updated player | Prefer loading compatible content within the current player. If a hosted player is needed for browser compatibility or a newer player version, offer an explicit link to open it in another tab and explain that it is the online experience. |

V2 design work will establish the website update endpoint, version compatibility, resource loading from a locally opened player, and any browser caching or hosted-player requirements. V1 must remain fully usable without this feature or an internet connection. No update service, automatic checks or online-content loading will be implemented as part of V1.

V2 review checks will cover no available updates, new and replacement content, unavailable internet, interrupted downloads, missing remote resources, and continued playback of the original USB edition.

## Content that can be added later

The core player needs the selected album files and track names. Artwork, a welcome message/video, song stories, lyrics, credits, photos, booklet, EPK, social links and booking information can be added independently as they become available. Optional content does not need to be complete before the player is usable.
