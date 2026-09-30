# RM Audio Playlist

WordPress plugin for managing MP3 playlists in the admin and embedding a vanilla JS player via an ACF block.

**Version:** 1.4.0  
**Requires:** WordPress 6.0+, PHP 7.4+, [Advanced Custom Fields](https://www.advancedcustomfields.com/) (Pro recommended for the block editor block)  
**Repo:** https://github.com/lancemonotone/rm-audio-playlist

---

## What it does

- Registers a private CPT: **Audio playlists** (`rm_audio_playlist`)
- ACF field group for ordered tracks (MP3), optional titles, per-track download flags, and playlist artwork
- Routes playlist MP3/artwork uploads into `wp-content/uploads/rm-audio-playlist/{playlist ID}/`
- Embeds a front-end player with the **Audio playlist** block (`acf/rm-audio-playlist`)
- Player features: play order, scrubber, skip ±10/±30s, shuffle, repeat (off / all / one), speed, volume, keyboard shortcuts, optional download, artwork lightbox, live waveform

There is **no shortcode**. Embedding is block-only.

---

## Install

1. Copy this folder to `wp-content/plugins/rm-audio-playlist/`  
   (or clone into that path)
2. Activate **Advanced Custom Fields** (Pro if you use the block)
3. Activate **RM Audio Playlist**
4. On activation the plugin registers the CPT, creates `uploads/rm-audio-playlist/`, and flushes rewrite rules

### From Git

```bash
cd wp-content/plugins
git clone https://github.com/lancemonotone/rm-audio-playlist.git rm-audio-playlist
```

Then activate in **Plugins**.

No npm install is required for the public player. Admin CSS/JS use a PHP minify step (see [Admin assets](#admin-assets-no-npm)).

---

## Create a playlist

1. In wp-admin go to **Audio playlists → Add New**
2. Set the playlist title
3. Optional: upload **Playlist artwork** (jpg/png/webp/gif)
4. Under **Tracks**, add rows:
   - **MP3 file** (required; MP3 only)
   - **Track title** (optional; empty titles are filled on save from ID3 tags when available, else filename)
   - **Allow download** (optional)
5. Use the toolbar to **Remove all MP3s** (clears playlist-scoped files + rows) or **Allow download all**
6. Publish

New uploads from this screen are stored under:

```text
wp-content/uploads/rm-audio-playlist/{playlist post ID}/
```

Uploads from other screens are unchanged. Clear-tracks only deletes files that live in that playlist folder (Media Library files from elsewhere are skipped).

---

## Embed on a page

1. Edit a page (or post) in the block editor
2. Insert **Audio playlist** (category **RM Audio Playlist**)
3. In the block sidebar, choose a playlist
4. Preview / publish

The player markup and assets load when the block is present on the page. CSS/JS are declared on the block (`block.json`) and are not enqueued site-wide.

### Editor notes

- Block mode is ACF **auto** (preview vs edit via the toolbar)
- Requires `acf_register_block_type` / ACF Pro-style blocks

---

## Player behavior (front end)

| Control | Behavior |
|--------|----------|
| Play / pause | Space when the player region is focused |
| Prev / next | Skip tracks in current order |
| ±10 / ±30 | Seek within the current track |
| Shuffle | Randomizes remaining order; keeps current track first when enabled |
| Repeat | Off → all → one |
| Speed | 0.5×–2× |
| Volume / mute | Persists volume in `localStorage` (`rm-audio-pl-vol-1`) |
| Queue | Drag or Alt+Arrow to reorder play order for this session |
| Artwork | Opens a lightbox when artwork is set |
| Download | Shown per track when enabled in ACF |

Accent color inherits theme `--accent` when defined; otherwise a plugin teal fallback.

---

## Architecture

```text
rm-audio-playlist.php          Bootstrap: path constants, class glob, activate/deactivate
classes/                       Architectural loaders only (no playlist guts)
  class.acf.php                Loads each block's fields.json + acf-json/
  class.block-registration.php Discovers blocks/*, loads block PHP, registers types
blocks/rm-audio-playlist/      The playlist product (CPT + admin + player)
  block.json                   Registration + front style/script lists
  fields.json                  Block sidebar fields
  acf-json/                    CPT field groups (group_*.json)
  template.php                 Block render
  class.block.php              Section shell helpers
  classes/                     Playlist PHP (self-boot via Block_Registration)
    class.constants.php        Slug, admin handle, ACF field name/key IDs
    class.cpt.php              Audio playlists CPT
    class.admin.php            REST + ACF admin UI + ID3 title fill
    class.assets.php           Admin asset build + lazy enqueue
    class.mime.php             MP3 upload mime fixes
    class.upload-dir.php       Scoped upload directory
    class.frontend.php         Playlist payload + player markup
  css/*.css                    Player styles (by concern)
  js/*.js                      Player scripts (numbered load order)
assets/src/admin/              Admin CSS/JS sources + manifests
assets/build/                  Generated admin min bundles (gitignored)
```

### PHP boot

1. `rm-audio-playlist.php` globs plugin `classes/class.*.php` (loaders).
2. `Block_Registration` immediately globs each `blocks/*/classes/class.*.php` (+ `class.block.php`).
3. On `acf/init`, loaders register field groups and block types.

### ACF fields (JSON only)

`Acf` on `acf/init` loads, per block folder:

1. `acf-json/group_*.json` (CPT groups when present)
2. `fields.json` (block sidebar)

Block `Constants` holds field **name/key** strings for PHP callers.

### Block registration

`Block_Registration` constructor loads each block's PHP immediately. On `acf/init` it registers types for folders with `block.json`.

Front assets are listed in `block.json` (`style` / `script` arrays). Load order for JS is the array order (filenames are numbered for clarity only).

### Admin assets (no npm)

`Assets` builds admin bundles when the environment is **not** `local`:

- Sources: `assets/src/admin/css/` and `assets/src/admin/js/` (ordered via `index.php` manifests)
- Output: `assets/build/css/admin.min.css`, `assets/build/js/admin.min.js`
- On `local`, raw sources are enqueued instead

Edit admin sources under `assets/src/admin/`. Do not hand-edit `assets/build/`. Rebuild happens on the next request when sources are newer than the build files.

Player (block) CSS/JS are **not** run through this pipeline; they ship as the files referenced from `block.json`.

---

## REST API (admin)

Namespace: `rm-audio-playlist/v1`  
Permission: user can `edit_post` the playlist.

| Method | Route | Purpose |
|--------|--------|---------|
| POST | `/playlists/{id}/clear-tracks` | Delete playlist-folder MP3s and empty the tracks repeater |
| POST | `/playlists/{id}/set-downloadable-all` | Body `{ "downloadable": true\|false }` |

---

## Development

### Requirements for contributors

- Local WordPress with ACF
- PHP 7.4+
- No Node toolchain required for the player

### Changing player CSS

Edit files under `blocks/rm-audio-playlist/css/`. Keep concerns split (`base`, `art-lightbox`, `progress`, `controls`, `queue`). Register new files in `block.json` `style` if you add more.

Prefer mobile-first CSS, nested `@media` at `768px` / `1024px`, logical properties, and theme `--accent` when styling accents.

### Changing player JS

Scripts share `window.RmAudioPlaylist`. Each file is an IIFE that reads/writes that namespace. Keep numbered order in `block.json` `script` when adding modules.

| File | Role |
|------|------|
| `00-bootstrap.js` | Namespace + constants |
| `01-icons-dom.js` | SVG icons, DOM helpers |
| `01b-utils.js` | Time format, shuffle helper |
| `02-player-core.js` | `PlayerBlock` constructor, advance guards |
| `03-player-build.js` | Build player DOM |
| `04-tooltips.js` | Floating tips |
| `05-queue.js` | Queue list / reorder |
| `06-controls-ui.js` | Button / mode UI |
| `07-audio-input.js` | Audio events, keyboard, volume, progress |
| `08-waveform.js` | Analyser + canvas |
| `09-playback.js` | Load, seek, shuffle/repeat, lightbox, status |
| `10-init.js` | Boot on `.rm-audio-playlist[data-rm-playlist]` |

### Git

```bash
git checkout -b feature/your-change
# …edit…
git push -u origin HEAD
```

`main` is the shippable branch. `assets/build/` is gitignored.

---

## Compatibility notes

- **ACF required** for fields and the block. Without ACF, admins with `activate_plugins` see a notice.
- **MP3 only** for track files (mime filters help hosts that mis-detect type).
- Upload routing only applies when the request is clearly a playlist asset upload for a playlist CPT the user can edit.
- Block CSS may inherit site `--accent`; otherwise the player uses built-in teal fallbacks (including dark `prefers-color-scheme`).

---

## License

See repository license file if present; otherwise treat as proprietary unless the author states otherwise.

---

## Support / live example

Demo: [rusmiller.com/music](https://rusmiller.com/music/)
