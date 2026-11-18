> **Version 1.2:** For albums, singles, artwork, song uploads, ordering, and replacements, use **Settings → Ian Raymond Player**. See [the current admin guide](README.md). The steps below are retained for the original Root Pass bootstrap; its file uploader is now under **Existing release file updates**.

# Pushing the new site out: step by step

This puts the Root Pass player on **ianraymond.live**: its own page at `/listen`, and (when you switch it on) at the top of the homepage, with your story, videos, booking, updates and contact sections underneath.

Nothing on the live homepage changes until step 6. Everything before that is safe to do at any time.

**Files you'll need** (all on this laptop):

| What | Where |
|---|---|
| The plugin | `~/Projects/Recording/Player/release/web/ian-raymond-listen.zip` |
| The songs + album data (13 files) | `~/Projects/Recording/Player/release/web/upload/` |

---

## 1. Build the latest version (laptop, 10 seconds)

Skip this if nothing has changed since the last build. To be sure the files match the current album:

```
cd ~/Projects/Recording/Player
python3 tools/build_web.py
```

It should end with `Plugin: … (4.9 MB)` and `Upload: … album.json + 12 MP3s`.

## 2. Install the plugin (wp-admin, 1 minute)

1. Log in at **ianraymond.live/wp-admin**.
2. **Plugins → Add New Plugin → Upload Plugin** (button at the top).
3. **Choose File** → select `ian-raymond-listen.zip` → **Install Now**.
4. When it finishes, click **Activate Plugin**.

✅ You should now see **Settings → Root Pass Player** in the left menu.

> Updating an older copy of the plugin? WordPress will ask to **Replace current with uploaded**. Say yes; your uploaded songs are kept.

## 3. Upload the songs (wp-admin, 2–5 minutes)

1. Go to **Settings → Root Pass Player**.
2. Under **Songs**, click **Choose Files**, open the `release/web/upload/` folder, press **Ctrl+A** to select all 13 files (12 MP3s + `album.json`) → **Open**.
3. Click **Upload**. Leave the page open; each file shows a ✓ as it finishes (files go up in small pieces, so size limits don't matter).
4. When it says **Done**, reload the page.

✅ The **Files on the site** table should list all 13 files with sizes, and none marked **missing**.

## 4. Check the listening page

Open **ianraymond.live/listen** (a private/incognito window is best, so you see what visitors see).

- [ ] All 11 songs are listed, NyQuil last, each with a time.
- [ ] Clicking a song plays it, and its artwork shows.
- [ ] Ten seconds into **NyQuil**, the purple bottle appears; clicking it adds **Naked Billy B-Side Remix**, and playing that turns everything psychedelic.
- [ ] On your phone, it looks right and plays.

If `/listen` says **Page not found**: go to **Settings → Permalinks** and click **Save Changes** once (no need to change anything), then reload `/listen`.

## 5. Preview the new homepage (nothing public changes)

1. **Settings → Root Pass Player → Preview the homepage with the player**.
2. Check:
   - [ ] Your header and menu at the top, then the player (IAN RAYMOND, the album, the song list).
   - [ ] Below it: The story, Videos, Booking, Updates, Contact, and the footer, same as today.
   - [ ] The player bar stays at the bottom of the screen while you scroll.
   - [ ] Playing a YouTube video pauses the album.
   - [ ] On your phone, the menu button still works and nothing runs off the side.

## 6. Go live

1. **Settings → Root Pass Player → Homepage**: tick **Show the player at the top of the homepage** → **Save**.
2. Open **ianraymond.live** in a private window.

If you still see the old homepage, it's a cached copy: clear the cache in the IONOS panel in wp-admin (or in your caching plugin, if you use one), then reload.

## If something looks wrong: undo

| To undo… | Do this |
|---|---|
| The homepage change only | **Settings → Root Pass Player** → untick the box → **Save**. The homepage goes back to the theme's normal layout instantly; `/listen` keeps working. |
| Everything | **Plugins** → **Deactivate** "Ian Raymond — Root Pass Player". |

Deactivating doesn't delete the songs; they stay in `wp-content/uploads/ian-listen/`.

The homepage also protects itself: if a future theme change removes the section the player replaces, the site shows the theme's normal homepage instead.

## Later updates

| What changed | What to do |
|---|---|
| A song was re-rendered | Step 1, then step 3 with just that song's MP3 (same name replaces the old one). |
| Songs added, removed, renamed or reordered | Step 1, then step 3 with the changed MP3s **and** `album.json` (upload `album.json` first; the page does this automatically). |
| Song artwork, player look or wording | Step 1, then step 2 again (Replace current with uploaded). |

Listeners get the update on their next visit.
