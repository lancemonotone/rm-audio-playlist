<?php
/**
 * Admin asset build and enqueue from assets/src/admin manifests.
 * Non-local environments minify into assets/build/.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Admin asset build and enqueue.
 */
final class Assets {

	private bool $debug;

	private string $plugin_path;

	private string $plugin_url;

	private string $build_path;

	private string $build_url;

	private bool $admin_enqueued = false;

	public function __construct( ?Config $config = null ) {
		$config            = $config ?? Config::get();
		$this->debug       = function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
		$this->plugin_path = $config->dir();
		$this->plugin_url  = $config->url();
		$this->build_path  = $this->plugin_path . 'assets/build';
		$this->build_url   = $this->plugin_url . 'assets/build';

		add_action( 'init', array( $this, 'maybe_build_assets' ), 5 );
		add_action( 'rm_audio_playlist_enqueue_admin', array( $this, 'on_enqueue_admin' ) );
	}

	/**
	 * Hook: enqueue admin CSS/JS for the playlist edit screen.
	 */
	public function on_enqueue_admin(): void {
		$this->do_enqueue_admin();
	}

	public function maybe_build_assets(): void {
		if ( $this->debug ) {
			return;
		}

		if ( ! is_dir( $this->build_path ) ) {
			wp_mkdir_p( $this->build_path );
		}

		$this->build_bundle( 'admin/css', 'css', 'admin.min.css' );
		$this->build_bundle( 'admin/js', 'js', 'admin.min.js' );
	}

	/**
	 * @param string $src_subdir     Relative to assets/src (e.g. admin/js).
	 * @param string $build_subdir   Relative to assets/build (css or js).
	 * @param string $build_basename Output filename.
	 */
	private function build_bundle( string $src_subdir, string $build_subdir, string $build_basename ): void {
		$source_dir  = $this->plugin_path . 'assets/src/' . $src_subdir;
		$build_file  = $this->build_path . '/' . $build_subdir . '/' . $build_basename;
		$config_file = $source_dir . '/index.php';

		if ( ! is_dir( $source_dir ) ) {
			return;
		}

		$is_css = '.css' === substr( $build_basename, -4 );
		$files  = $is_css ? $this->discover_css_files( $source_dir ) : $this->discover_js_files( $source_dir );
		if ( array() === $files ) {
			return;
		}

		$sources_for_rebuild = $files;
		if ( is_file( $config_file ) ) {
			$sources_for_rebuild[] = $config_file;
		}
		$sources_for_rebuild = array_values( array_unique( $sources_for_rebuild ) );

		if ( ! $this->needs_rebuild( $build_file, $sources_for_rebuild ) ) {
			return;
		}

		$raw = $this->concatenate_files( $files );
		$out = $is_css ? $this->minify_css( $raw ) : $this->minify_js( $raw );
		wp_mkdir_p( dirname( $build_file ) );
		file_put_contents( $build_file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * @return list<string>
	 */
	private function discover_css_files( string $directory ): array {
		$config_file = $directory . '/index.php';
		if ( is_file( $config_file ) ) {
			return $this->load_sources_from_manifest( $directory, $config_file );
		}
		$globbed = glob( $directory . '/**/*.css' );
		return is_array( $globbed ) ? $globbed : array();
	}

	/**
	 * @return list<string>
	 */
	private function discover_js_files( string $directory ): array {
		$config_file = $directory . '/index.php';
		if ( is_file( $config_file ) ) {
			return $this->load_sources_from_manifest( $directory, $config_file );
		}
		$globbed = glob( $directory . '/**/*.js' );
		return is_array( $globbed ) ? $globbed : array();
	}

	/**
	 * @return list<string>
	 */
	private function load_sources_from_manifest( string $directory, string $config_file ): array {
		$config = include $config_file;
		if ( ! is_array( $config ) ) {
			return array();
		}

		$files = array();
		foreach ( $config as $relative_path ) {
			if ( ! is_string( $relative_path ) || '' === $relative_path ) {
				continue;
			}
			$relative_path = str_replace( '\\', '/', $relative_path );
			$full_path     = $directory . '/' . $relative_path;
			if ( is_file( $full_path ) ) {
				$files[] = $full_path;
			}
		}

		return $files;
	}

	/**
	 * @param list<string> $source_files
	 */
	private function needs_rebuild( string $build_file, array $source_files ): bool {
		if ( ! is_file( $build_file ) ) {
			return true;
		}

		$build_time = filemtime( $build_file );
		if ( false === $build_time ) {
			return true;
		}

		foreach ( $source_files as $source_file ) {
			$m = filemtime( $source_file );
			if ( false !== $m && $m > $build_time ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<string> $files
	 */
	private function concatenate_files( array $files ): string {
		$content = '';
		foreach ( $files as $file ) {
			$file_content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $file_content ) {
				continue;
			}
			$content .= '/* Source: ' . basename( $file ) . " */\n";
			$content .= $file_content . "\n\n";
		}
		return $content;
	}

	private function minify_css( string $css ): string {
		$css = (string) preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		$css = str_replace( ': ', ':', $css );
		$css = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $css );
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = str_replace( ', ', ',', $css );
		$css = str_replace( ' {', '{', $css );
		$css = str_replace( '{ ', '{', $css );
		$css = str_replace( ' }', '}', $css );
		$css = str_replace( '} ', '}', $css );
		$css = str_replace( ';}', '}', $css );
		return trim( $css );
	}

	private function minify_js( string $js ): string {
		$js = (string) preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js );
		$js = (string) preg_replace( '/\/\/.*\r?\n/', '', $js );

		/** @var array<string, string> $template_literals */
		$template_literals   = array();
		$placeholder_counter = 0;
		$js                  = (string) preg_replace_callback(
			'/\$\{[^}]+\}/',
			static function ( array $matches ) use ( &$template_literals, &$placeholder_counter ): string {
				$placeholder                       = '__TEMPLATE_LITERAL_' . $placeholder_counter . '__';
				$template_literals[ $placeholder ] = $matches[0];
				++$placeholder_counter;
				return $placeholder;
			},
			$js
		);

		$js = (string) preg_replace( '/\s+/', ' ', $js );
		$js = str_replace( ': ', ':', $js );
		$js = str_replace( ', ', ',', $js );
		$js = str_replace( '{ ', '{', $js );
		$js = str_replace( ' {', '{', $js );
		$js = str_replace( '} ', '}', $js );
		$js = str_replace( ' }', '}', $js );
		$js = str_replace( '; ', ';', $js );
		$js = str_replace( ' ;', ';', $js );

		foreach ( $template_literals as $placeholder => $original ) {
			$js = str_replace( $placeholder, (string) $original, $js );
		}

		return trim( $js );
	}

	private function do_enqueue_admin(): void {
		if ( $this->admin_enqueued ) {
			return;
		}
		$this->admin_enqueued = true;

		if ( $this->debug ) {
			$this->enqueue_manifest_styles( 'admin/css', Constants::HANDLE_ADMIN );
			$this->enqueue_manifest_scripts( 'admin/js', Constants::HANDLE_ADMIN );
			return;
		}

		$this->enqueue_built( 'css/admin.min.css', Constants::HANDLE_ADMIN, true );
		$this->enqueue_built( 'js/admin.min.js', Constants::HANDLE_ADMIN, false );
	}

	private function enqueue_built( string $relative_build, string $handle, bool $is_style ): void {
		$path = $this->build_path . '/' . $relative_build;
		if ( ! is_file( $path ) ) {
			return;
		}
		$version = filemtime( $path );
		$url     = $this->build_url . '/' . $relative_build;
		$ver     = false !== $version ? (string) $version : null;
		if ( $is_style ) {
			wp_enqueue_style( $handle, $url, array(), $ver );
		} else {
			wp_enqueue_script( $handle, $url, array(), $ver, true );
		}
	}

	/**
	 * @param string $src_subdir Relative to assets/src.
	 * @param string $final_handle Handle for the last (or only) file.
	 */
	private function enqueue_manifest_styles( string $src_subdir, string $final_handle ): void {
		$directory   = $this->plugin_path . 'assets/src/' . $src_subdir;
		$config_file = $directory . '/index.php';
		if ( ! is_file( $config_file ) ) {
			return;
		}

		$config = include $config_file;
		if ( ! is_array( $config ) ) {
			return;
		}

		$queue = array();
		foreach ( $config as $relative_path ) {
			if ( ! is_string( $relative_path ) || '' === $relative_path ) {
				continue;
			}
			$relative_path = str_replace( '\\', '/', $relative_path );
			$full_path     = $directory . '/' . $relative_path;
			if ( ! is_file( $full_path ) ) {
				continue;
			}
			$queue[] = array(
				'relative' => $relative_path,
				'full'     => $full_path,
			);
		}

		$total = count( $queue );
		if ( 0 === $total ) {
			return;
		}

		$deps = array();
		foreach ( $queue as $i => $item ) {
			$handle  = ( $i === $total - 1 ) ? $final_handle : $final_handle . '-part-' . (string) $i;
			$version = filemtime( $item['full'] );
			wp_enqueue_style(
				$handle,
				$this->plugin_url . 'assets/src/' . $src_subdir . '/' . $item['relative'],
				$deps,
				false !== $version ? (string) $version : null
			);
			$deps = array( $handle );
		}
	}

	/**
	 * @param string $src_subdir Relative to assets/src.
	 * @param string $final_handle Handle when only one file; multi-file uses slugified names with last = final.
	 */
	private function enqueue_manifest_scripts( string $src_subdir, string $final_handle ): void {
		$directory   = $this->plugin_path . 'assets/src/' . $src_subdir;
		$config_file = $directory . '/index.php';
		if ( ! is_file( $config_file ) ) {
			return;
		}

		$config = include $config_file;
		if ( ! is_array( $config ) ) {
			return;
		}

		$queue = array();
		foreach ( $config as $relative_path ) {
			if ( ! is_string( $relative_path ) || '' === $relative_path ) {
				continue;
			}
			$relative_path = str_replace( '\\', '/', $relative_path );
			$full_path     = $directory . '/' . $relative_path;
			if ( ! is_file( $full_path ) ) {
				continue;
			}
			$queue[] = array(
				'relative' => $relative_path,
				'full'     => $full_path,
			);
		}

		$total = count( $queue );
		if ( 0 === $total ) {
			return;
		}

		foreach ( $queue as $i => $item ) {
			$handle  = ( $i === $total - 1 ) ? $final_handle : $final_handle . '-part-' . (string) $i;
			$version = filemtime( $item['full'] );
			wp_enqueue_script(
				$handle,
				$this->plugin_url . 'assets/src/' . $src_subdir . '/' . $item['relative'],
				array(),
				false !== $version ? (string) $version : null,
				true
			);
		}
	}
}

new Assets();
