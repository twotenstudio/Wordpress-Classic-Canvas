<?php
/**
 * Plugin Name:       Classic Editor Canvas for ACF Blocks
 * Plugin URI:        https://twotenstudio.co.uk
 * Description:       Restores the non-iframed block editor canvas on post edit screens (WordPress 7.1+), so ACF PRO blocks render their field forms inline in the canvas ("edit mode") instead of being forced into preview mode with sidebar-only fields.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            TwoTen Studio
 * Author URI:        https://twotenstudio.co.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * WordPress 7.1 hard-codes the post editor canvas into an iframe
 * (`shouldIframe: true` in the wp-editor bundle). ACF PRO cannot initialise
 * its field forms inside that iframe, so it force-switches every ACF block
 * to preview mode with fields in the sidebar. WordPress still ships the
 * old non-iframed canvas code path — core just stopped using it.
 *
 * This plugin serves a copy of the wp-editor bundle with that single flag
 * flipped (`shouldIframe:!0` -> `shouldIframe:!1`), on post.php and
 * post-new.php only. The site editor and every other admin screen receive
 * the stock bundle. No core files are modified on disk.
 *
 * Self-healing: the patched copy is keyed to the core version + bundle
 * mtime, so it regenerates automatically after every WordPress update.
 * Fail-safe: it only patches when the flag appears exactly once in the
 * bundle; if a future WordPress changes that code, the stock bundle is
 * served and the editor simply returns to the iframed canvas.
 *
 * Remove this plugin once ACF PRO supports field editing inside the
 * iframed canvas (check the ACF changelog before major WP upgrades).
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'script_loader_src', 'tts_cc_script_src', 10, 2 );

/**
 * Swap the wp-editor bundle for the patched copy on post edit screens.
 *
 * @param string $src    Script URL.
 * @param string $handle Script handle.
 * @return string
 */
function tts_cc_script_src( $src, $handle ) {
	if ( 'wp-editor' !== $handle || ! is_admin() ) {
		return $src;
	}

	// WordPress before 7.1 decides iframing conditionally and ACF edit mode
	// still works — nothing to do there.
	if ( version_compare( get_bloginfo( 'version' ), '7.1', '<' ) ) {
		return $src;
	}

	// Post and page edit screens only — the site editor needs its iframe.
	global $pagenow;
	if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
		return $src;
	}

	// Debug builds use the unminified bundle; leave them stock.
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return $src;
	}

	/**
	 * Kill switch: return false to keep the stock (iframed) editor
	 * everywhere, e.g. per environment or per post type.
	 *
	 * @param bool $enabled Whether the classic canvas is enabled.
	 */
	if ( ! apply_filters( 'tts_classic_canvas_enabled', true ) ) {
		return $src;
	}

	$core_file = ABSPATH . WPINC . '/js/dist/editor.min.js';
	if ( ! file_exists( $core_file ) ) {
		return $src;
	}

	$patched = tts_cc_patched_file( $core_file );
	if ( ! $patched ) {
		return $src;
	}

	$url = $patched['url'];

	// Keep the original cache-busting query string.
	$query = wp_parse_url( $src, PHP_URL_QUERY );
	if ( $query ) {
		$url .= '?' . $query;
	}

	return $url;
}

/**
 * Return the patched editor bundle (path + URL), generating it if needed.
 *
 * @param string $core_file Path to the stock editor.min.js.
 * @return array|false { path: string, url: string }, or false to use stock.
 */
function tts_cc_patched_file( $core_file ) {
	$uploads = wp_upload_dir( null, false );
	if ( ! empty( $uploads['error'] ) ) {
		return false;
	}

	$dir  = trailingslashit( $uploads['basedir'] ) . 'classic-editor-canvas';
	$name = sprintf(
		'editor-%s-%d.min.js',
		sanitize_file_name( get_bloginfo( 'version' ) ),
		(int) filemtime( $core_file )
	);
	$path = $dir . '/' . $name;
	$url  = trailingslashit( $uploads['baseurl'] ) . 'classic-editor-canvas/' . $name;

	if ( file_exists( $path ) ) {
		return array( 'path' => $path, 'url' => $url );
	}

	$js = file_get_contents( $core_file );
	if ( false === $js ) {
		return false;
	}

	// The patch must be unambiguous: exactly one occurrence, or we do nothing.
	if ( 1 !== substr_count( $js, 'shouldIframe:!0' ) ) {
		return false;
	}

	$js = str_replace( 'shouldIframe:!0', 'shouldIframe:!1', $js );

	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	// Tidy up copies left over from previous core versions.
	foreach ( (array) glob( $dir . '/editor-*.min.js' ) as $old ) {
		if ( basename( $old ) !== $name ) {
			@unlink( $old );
		}
	}

	if ( false === file_put_contents( $path, $js ) ) {
		return false;
	}

	return array( 'path' => $path, 'url' => $url );
}
