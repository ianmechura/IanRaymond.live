# ianraymond.live — theme and core plugin

Source for the parts of the site that were not previously in this repo, pulled from the live WordPress install on 2026-10-14 (theme/plugin editor for PHP/CSS/JS, public URLs for binary assets and the CRLF font licences).

- `theme/ian-raymond/` — the active theme “Ian Raymond — True Stories” (`wp-content/themes/ian-raymond`).
- `plugins/ian-raymond-core/` — “Ian Raymond Core” 1.0.0: site settings (`ir_settings` / `ir_defaults()`), the Song/Show/Video post types and the `data-ir-content` editing the theme relies on.

The Music Player (`Player/web/`) and Listener Notifications (`Notifications/src/`) plugins have their own folders. Edit here, then deploy; changes made directly in wp-admin will be lost on the next deploy unless pulled back first.
