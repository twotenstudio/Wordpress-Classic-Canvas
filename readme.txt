=== Classic Editor Canvas for ACF Blocks ===
Contributors: twotenstudio
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restores the non-iframed block editor canvas on WordPress 7.1+ so ACF PRO
blocks render their field forms inline in the canvas ("edit mode").

== Description ==

WordPress 7.1 renders the post editor canvas inside an iframe on every
post, unconditionally. ACF PRO cannot initialise its field forms inside
that iframe, so from 7.1 every ACF block is forced into preview mode with
its fields squeezed into the right-hand sidebar — the classic inline
"edit mode" disappears.

WordPress 7.1 still ships the old non-iframed canvas rendering path; core
just stopped using it. This plugin serves a copy of the `wp-editor` script
bundle with the single flag that controls this flipped
(`shouldIframe: true` becomes `false`), which restores the pre-7.1
editing experience: ACF block fields render inline in the main canvas,
full width, with working WYSIWYG, media pickers and repeaters.

**Safety properties**

* No core files are modified on disk. The patched copy lives in
  `wp-content/uploads/classic-editor-canvas/` and core stays pristine
  (`wp core verify-checksums` passes).
* Scoped: only `post.php` / `post-new.php` receive the patched bundle.
  The site editor — which requires the iframe — and all other admin
  screens get stock WordPress.
* Self-healing: the patched copy is keyed to the WordPress version and
  bundle mtime, so it regenerates automatically after every core update.
* Fail-safe: the patch is only applied when the flag appears exactly once
  in the bundle. If a future WordPress changes that code, the plugin
  serves the stock bundle and the editor simply returns to the iframed
  canvas — nothing breaks.
* Does nothing at all on WordPress below 7.1 (safe to install ahead of an
  upgrade) and when `SCRIPT_DEBUG` is enabled.

**Block requirements**

Inline edit mode is an ACF Blocks v2 behaviour. Your blocks should:

* be registered without `"blockVersion": 3` in `block.json` (v3 removes
  edit mode by design, iframe or not);
* set `"acf": { "mode": "edit" }` (and ideally `"supports": { "mode": false }`)
  so blocks open as forms rather than previews.

Blocks saved while stuck in preview mode may carry `"mode":"preview"` in
their saved markup, which overrides the block default. Clean them with:

`wp search-replace '"mode":"preview"' '"mode":"edit"' wp_posts --include-columns=post_content`

**When to remove this plugin**

This is a compatibility shim, not a permanent architecture. Remove it once
ACF PRO supports field editing inside the iframed canvas — check the ACF
changelog before major WordPress upgrades. After any WordPress core
update, open a post in the editor and confirm blocks still show inline
forms; if the fail-safe has kicked in they will show previews again, and
the plugin needs a new patch pattern for that WordPress version.

**Disabling programmatically**

`add_filter( 'tts_classic_canvas_enabled', '__return_false' );`

== Installation ==

1. Upload the `classic-editor-canvas` folder to `/wp-content/plugins/`,
   or upload the ZIP via Plugins → Add New → Upload Plugin.
2. Activate it. There are no settings.

To run it as a must-use plugin instead (always on, cannot be deactivated
from the dashboard), copy `classic-editor-canvas.php` into
`wp-content/mu-plugins/`. The file is fully standalone. Do not run both
the normal and mu-plugin variants on the same site.

== Frequently Asked Questions ==

= Does this modify WordPress core? =

No. Core files are untouched; the plugin serves an alternative copy of one
JavaScript bundle from the uploads directory, only on post edit screens.

= What happens when WordPress updates? =

The patched bundle regenerates automatically for the new version. If the
new version has changed the relevant code, the plugin falls back to stock
behaviour (iframed canvas, ACF preview mode) rather than breaking the
editor — so glance at the block editor after each core update.

= Why do my blocks still show previews in the sidebar style? =

Check that the blocks are ACF Blocks v2 with `"mode": "edit"` (see Block
requirements), and that the saved post content doesn't carry stale
`"mode":"preview"` attributes.

== Changelog ==

= 1.0.0 =
* Initial release: one-token `shouldIframe` patch, scoped to post edit
  screens, keyed to core version with exactly-once fail-safe.
