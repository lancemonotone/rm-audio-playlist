<?php
/**
 * Build and register CSS/JS for the plugin and each block package.
 *
 * Plugin `assets/src/{front,admin}` enqueue when present. Block front assets
 * attach to matching block types under `blocks/`. Block admin assets enqueue
 * via `{textdomain}_enqueue_admin` (see {@see Config::hook()}).
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Asset build, block handle registration, and enqueue.
 */
final class Assets {

	private const DIR_ASSETS = 'assets';

	private const DIR_BLOCKS = 'blocks';

	private const DIR_SRC = 'src';

	private const DIR_BUILD = 'build';

	private const MANIFEST = 'index.php';

	private const BLOCK_JSON = 'block.json';

	private const CONTEXT_FRONT = 'front';

	private const CONTEXT_ADMIN = 'admin';

	/**
	 * Contexts under src/ and build/.
	 *
	 * @var list<string>
	 */
	private const CONTEXTS = array(
		self::CONTEXT_FRONT,
		self::CONTEXT_ADMIN,
	);

	/**
	 * Kind folder under each context → file extension + style vs script.
	 *
	 * @var array<string, array{ext: string, is_style: bool}>
	 */
	private const KINDS = array(
		'css' => array(
			'ext'      => 'css',
			'is_style' => true,
		),
		'js'  => array(
			'ext'      => 'js',
			'is_style' => false,
		),
	);

	private const HANDLE_SUFFIX_FRONT_STYLE = '-front-style';

	private const HANDLE_SUFFIX_FRONT_SCRIPT = '-front-script';

	private const HANDLE_SUFFIX_FRONT = '-front';

	private const HANDLE_SUFFIX_ADMIN = '-admin';

	private Config $config;

	private bool $debug;

	/**
	 * @var array<string, true>
	 */
	private array $admin_enqueued = array();

	private bool $plugin_front_enqueued = false;

	/**
	 * @var list<string>|null
	 */
	private ?array $block_folders_cache = null;

	/**
	 * @var array<string, string>|null block.json name → folder path
	 */
	private ?array $block_folders_by_name = null;

	public function __construct( ?Config $config = null ) {
		$this->config = $config ?? Config::get();
		$this->debug  = function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();

		add_action( 'init', array( $this, 'maybe_build_assets' ), 5 );
		add_filter( 'block_type_metadata', array( $this, 'filter_block_type_metadata' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_plugin_front' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_plugin_admin' ) );
		add_action( $this->config->hook( 'enqueue_admin' ), array( $this, 'on_enqueue_admin' ), 10, 2 );
	}

	/**
	 * Build plugin-level and per-block asset bundles when not local.
	 */
	public function maybe_build_assets(): void {
		if ( $this->debug ) {
			return;
		}

		$this->build_asset_root( $this->plugin_assets_root() );

		foreach ( $this->block_folders() as $block_folder ) {
			$this->build_asset_root( $this->block_assets_root( $block_folder ) );
		}
	}

	/**
	 * Attach front style/script handles when the block lives under this plugin's blocks/.
	 *
	 * @param array<string, mixed> $metadata Block metadata from block.json.
	 * @return array<string, mixed>
	 */
	public function filter_block_type_metadata( array $metadata ): array {
		$name = isset( $metadata['name'] ) && is_string( $metadata['name'] ) ? $metadata['name'] : '';
		if ( '' === $name ) {
			return $metadata;
		}

		$block_folder = $this->block_folder_for_name( $name );
		if ( null === $block_folder ) {
			return $metadata;
		}

		$handles = $this->register_front_handles( $block_folder, basename( $block_folder ) ) ?? array();
		foreach ( array( 'style', 'script' ) as $key ) {
			if ( isset( $handles[ $key ] ) ) {
				$metadata[ $key ] = $handles[ $key ];
			} else {
				unset( $metadata[ $key ] );
			}
		}
		return $metadata;
	}

	/**
	 * Enqueue plugin-level front bundles when front sources exist.
	 */
	public function enqueue_plugin_front(): void {
		if ( $this->plugin_front_enqueued ) {
			return;
		}
		$assets_root = $this->plugin_assets_root();
		if ( ! $this->context_has_assets( $assets_root, self::CONTEXT_FRONT ) ) {
			return;
		}
		$this->enqueue_bundle( $assets_root, self::CONTEXT_FRONT, $this->config->textdomain() . self::HANDLE_SUFFIX_FRONT );
		$this->plugin_front_enqueued = true;
	}

	/**
	 * Enqueue plugin-level admin bundles when admin sources exist.
	 */
	public function enqueue_plugin_admin(): void {
		$assets_root = $this->plugin_assets_root();
		if ( ! $this->context_has_assets( $assets_root, self::CONTEXT_ADMIN ) ) {
			return;
		}
		$handle = $this->config->textdomain() . self::HANDLE_SUFFIX_ADMIN;
		if ( isset( $this->admin_enqueued[ $handle ] ) ) {
			return;
		}
		$this->enqueue_bundle( $assets_root, self::CONTEXT_ADMIN, $handle );
		$this->admin_enqueued[ $handle ] = true;
	}

	/**
	 * @param string $block_slug Block folder name under blocks/.
	 * @param string $handle     Script/style handle for admin bundle.
	 */
	public function on_enqueue_admin( string $block_slug = '', string $handle = '' ): void {
		if ( '' === $block_slug || '' === $handle ) {
			return;
		}
		if ( isset( $this->admin_enqueued[ $handle ] ) ) {
			return;
		}

		$block_folder = $this->config->dir() . self::DIR_BLOCKS . '/' . $block_slug;
		if ( ! is_dir( $block_folder ) ) {
			return;
		}

		$assets_root = $this->block_assets_root( $block_folder );
		if ( ! $this->context_has_assets( $assets_root, self::CONTEXT_ADMIN ) ) {
			return;
		}

		$this->enqueue_bundle( $assets_root, self::CONTEXT_ADMIN, $handle );
		$this->admin_enqueued[ $handle ] = true;
	}

	/**
	 * Enqueue front or admin CSS/JS for one assets root (plugin or block).
	 */
	private function enqueue_bundle( string $assets_root, string $context, string $handle ): void {
		if ( $this->debug ) {
			foreach ( self::KINDS as $kind => $meta ) {
				$dir = $this->src_kind_dir( $assets_root, $context, $kind );
				$this->enqueue_manifest( $dir, $this->url_for_path( $dir ), $handle, $meta['is_style'] );
			}
			return;
		}

		foreach ( self::KINDS as $kind => $meta ) {
			$path = $this->build_bundle_path( $assets_root, $context, $kind );
			$this->enqueue_built_file( $path, $this->url_for_path( $path ), $handle, $meta['is_style'] );
		}
	}

	/**
	 * True when src or built files exist for a context.
	 */
	private function context_has_assets( string $assets_root, string $context ): bool {
		foreach ( array_keys( self::KINDS ) as $kind ) {
			if ( $this->directory_has_asset_sources( $this->src_kind_dir( $assets_root, $context, $kind ) ) ) {
				return true;
			}
		}
		foreach ( array_keys( self::KINDS ) as $kind ) {
			if ( is_file( $this->build_bundle_path( $assets_root, $context, $kind ) ) ) {
				return true;
			}
		}
		return false;
	}

	private function directory_has_asset_sources( string $directory ): bool {
		return array() !== $this->manifest_queue( $directory );
	}

	/**
	 * @return array{style?: string, script?: string}|null
	 */
	private function register_front_handles( string $block_folder, string $block_slug ): ?array {
		$style_handle  = $block_slug . self::HANDLE_SUFFIX_FRONT_STYLE;
		$script_handle = $block_slug . self::HANDLE_SUFFIX_FRONT_SCRIPT;
		$assets_root   = $this->block_assets_root( $block_folder );
		$out           = array();

		if ( $this->debug ) {
			foreach ( self::KINDS as $kind => $meta ) {
				$dir    = $this->src_kind_dir( $assets_root, self::CONTEXT_FRONT, $kind );
				$handle = $meta['is_style'] ? $style_handle : $script_handle;
				if ( $this->register_manifest( $dir, $this->url_for_path( $dir ), $handle, $meta['is_style'] ) ) {
					$out[ $meta['is_style'] ? 'style' : 'script' ] = $handle;
				}
			}
			return array() === $out ? null : $out;
		}

		foreach ( self::KINDS as $kind => $meta ) {
			$path = $this->build_bundle_path( $assets_root, self::CONTEXT_FRONT, $kind );
			if ( ! is_file( $path ) ) {
				continue;
			}
			$ver    = filemtime( $path );
			$handle = $meta['is_style'] ? $style_handle : $script_handle;
			$url    = $this->url_for_path( $path );
			$ver_s  = false !== $ver ? (string) $ver : null;
			if ( $meta['is_style'] ) {
				wp_register_style( $handle, $url, array(), $ver_s );
				$out['style'] = $handle;
			} else {
				wp_register_script( $handle, $url, array(), $ver_s, true );
				$out['script'] = $handle;
			}
		}

		return array() === $out ? null : $out;
	}

	/**
	 * assets/src/{contexts}/{kinds} → assets/build/{contexts}/{kinds}.
	 */
	private function build_asset_root( string $assets_root ): void {
		$src = $assets_root . '/' . self::DIR_SRC;
		if ( ! is_dir( $src ) ) {
			return;
		}

		foreach ( self::CONTEXTS as $context ) {
			foreach ( self::KINDS as $kind => $meta ) {
				$this->build_bundle_from_dir(
					$this->src_kind_dir( $assets_root, $context, $kind ),
					$this->build_bundle_path( $assets_root, $context, $kind ),
					$meta['is_style']
				);
			}
		}
	}

	/**
	 * @param string $source_dir Absolute directory with index.php manifest.
	 * @param string $build_file Absolute output path.
	 * @param bool   $is_css     True for CSS minify.
	 */
	private function build_bundle_from_dir( string $source_dir, string $build_file, bool $is_css ): void {
		$files = array_column( $this->manifest_queue( $source_dir ), 'full' );
		if ( array() === $files ) {
			return;
		}

		$sources_for_rebuild = array_values(
			array_unique(
				array_merge( $files, array( $source_dir . '/' . self::MANIFEST ) )
			)
		);

		if ( ! $this->needs_rebuild( $build_file, $sources_for_rebuild ) ) {
			return;
		}

		$raw = $this->concatenate_files( $files );
		$out = $is_css ? $this->minify_css( $raw ) : $this->minify_js( $raw );
		wp_mkdir_p( dirname( $build_file ) );
		file_put_contents( $build_file, $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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
			$content .= $file_content . "\n";
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

	/**
	 * Register chained handles from a manifest; final handle is $final_handle.
	 */
	private function register_manifest( string $directory, string $directory_url, string $final_handle, bool $is_style ): bool {
		$queue = $this->manifest_queue( $directory );
		$total = count( $queue );
		if ( 0 === $total ) {
			return false;
		}

		$deps = array();
		foreach ( $queue as $i => $item ) {
			$handle  = ( $i === $total - 1 ) ? $final_handle : $final_handle . '-part-' . (string) $i;
			$version = filemtime( $item['full'] );
			$ver     = false !== $version ? (string) $version : null;
			$url     = $directory_url . '/' . $item['relative'];
			if ( $is_style ) {
				wp_register_style( $handle, $url, $deps, $ver );
			} else {
				wp_register_script( $handle, $url, $deps, $ver, true );
			}
			$deps = array( $handle );
		}
		return true;
	}

	private function enqueue_manifest( string $directory, string $directory_url, string $final_handle, bool $is_style ): void {
		if ( ! $this->register_manifest( $directory, $directory_url, $final_handle, $is_style ) ) {
			return;
		}
		if ( $is_style ) {
			wp_enqueue_style( $final_handle );
		} else {
			wp_enqueue_script( $final_handle );
		}
	}

	private function enqueue_built_file( string $path, string $url, string $handle, bool $is_style ): void {
		if ( ! is_file( $path ) ) {
			return;
		}
		$version = filemtime( $path );
		$ver     = false !== $version ? (string) $version : null;
		if ( $is_style ) {
			wp_enqueue_style( $handle, $url, array(), $ver );
		} else {
			wp_enqueue_script( $handle, $url, array(), $ver, true );
		}
	}

	/**
	 * @return list<array{relative: string, full: string}>
	 */
	private function manifest_queue( string $directory ): array {
		$config_file = $directory . '/' . self::MANIFEST;
		if ( ! is_file( $config_file ) ) {
			return array();
		}

		$config = include $config_file;
		if ( ! is_array( $config ) ) {
			return array();
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
		return $queue;
	}

	private function plugin_assets_root(): string {
		return $this->config->dir() . self::DIR_ASSETS;
	}

	private function block_assets_root( string $block_folder ): string {
		return $block_folder . '/' . self::DIR_ASSETS;
	}

	private function src_kind_dir( string $assets_root, string $context, string $kind ): string {
		return $assets_root . '/' . self::DIR_SRC . '/' . $context . '/' . $kind;
	}

	/**
	 * Built bundle path: build/{context}/{kind}/{context}.min.{ext}.
	 */
	private function build_bundle_path( string $assets_root, string $context, string $kind ): string {
		$ext = self::KINDS[ $kind ]['ext'];
		return $assets_root . '/' . self::DIR_BUILD . '/' . $context . '/' . $kind . '/' . $context . '.min.' . $ext;
	}

	/**
	 * @return list<string>
	 */
	private function block_folders(): array {
		if ( null !== $this->block_folders_cache ) {
			return $this->block_folders_cache;
		}

		$blocks_dir = $this->config->dir() . self::DIR_BLOCKS;
		if ( ! is_dir( $blocks_dir ) ) {
			return $this->block_folders_cache = array();
		}

		$block_folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );
		return $this->block_folders_cache = is_array( $block_folders ) ? $block_folders : array();
	}

	/**
	 * @return array<string, string>
	 */
	private function block_folders_by_name(): array {
		if ( null !== $this->block_folders_by_name ) {
			return $this->block_folders_by_name;
		}

		$map = array();
		foreach ( $this->block_folders() as $block_folder ) {
			$json_file = $block_folder . '/' . self::BLOCK_JSON;
			if ( ! is_file( $json_file ) ) {
				continue;
			}
			$raw = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $raw ) {
				continue;
			}
			/** @var array<string, mixed>|null $data */
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) || ! isset( $data['name'] ) || ! is_string( $data['name'] ) || '' === $data['name'] ) {
				continue;
			}
			$map[ $data['name'] ] = $block_folder;
		}

		return $this->block_folders_by_name = $map;
	}

	private function block_folder_for_name( string $block_name ): ?string {
		$map = $this->block_folders_by_name();
		return $map[ $block_name ] ?? null;
	}

	/**
	 * Absolute filesystem path → plugin URL.
	 */
	private function url_for_path( string $absolute_path ): string {
		$root = wp_normalize_path( $this->config->dir() );
		$path = wp_normalize_path( $absolute_path );
		if ( ! str_starts_with( $path, $root ) ) {
			return '';
		}
		$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
		return $this->config->url() . str_replace( '\\', '/', $relative );
	}
}

new Assets();
