<?php
/**
 * Discovers each blocks subfolder: loads block PHP, registers block types.
 *
 * Field groups load from JSON via {@see Acf}.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Heart & Soil / rm-blocks style block folder registration.
 */
final class Block_Registration {

	public const CATEGORY = 'rm-audio-playlist';

	public function __construct() {
		$this->load_all_block_php();
		add_action( 'acf/init', array( $this, 'register_blocks' ), 10 );
		add_filter( 'block_categories_all', array( $this, 'register_block_categories' ), 5, 2 );
	}

	/**
	 * Put RM Audio Playlist first in the block inserter.
	 *
	 * @param array<int, array<string, mixed>> $block_categories     Categories.
	 * @param mixed                             $block_editor_context Editor context.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_categories( array $block_categories, $block_editor_context ): array {
		unset( $block_editor_context );
		array_unshift(
			$block_categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __( 'RM Audio Playlist', 'rm-audio-playlist' ),
				'icon'  => null,
			)
		);
		return $block_categories;
	}

	public function register_blocks(): void {
		foreach ( $this->block_folders() as $block_folder ) {
			if ( ! is_file( $block_folder . '/block.json' ) ) {
				continue;
			}
			register_block_type( $block_folder );
		}
	}

	/**
	 * Load class.block.php + classes/class.*.php for every block folder.
	 */
	private function load_all_block_php(): void {
		foreach ( $this->block_folders() as $block_folder ) {
			$this->load_block_php( $block_folder );
		}
	}

	/**
	 * @return list<string>
	 */
	private function block_folders(): array {
		$blocks_dir = RM_AUDIO_PLAYLIST_DIR . 'blocks';
		if ( ! is_dir( $blocks_dir ) ) {
			return array();
		}

		$block_folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );
		return is_array( $block_folders ) ? $block_folders : array();
	}

	/**
	 * Load class.block.php plus optional classes/class.*.php under the block folder.
	 */
	private function load_block_php( string $block_folder ): void {
		$class_file = $block_folder . '/class.block.php';
		if ( is_file( $class_file ) ) {
			require_once $class_file;
		}

		$classes_dir = $block_folder . '/classes';
		if ( ! is_dir( $classes_dir ) ) {
			return;
		}

		$files = glob( $classes_dir . '/class.*.php' );
		if ( ! is_array( $files ) ) {
			return;
		}

		// Constants first so other block classes can reference Fields/Constants at boot.
		usort(
			$files,
			static function ( string $a, string $b ): int {
				$an = basename( $a );
				$bn = basename( $b );
				if ( 'class.constants.php' === $an ) {
					return -1;
				}
				if ( 'class.constants.php' === $bn ) {
					return 1;
				}
				return strcmp( $an, $bn );
			}
		);

		foreach ( $files as $file ) {
			require_once $file;
		}
	}
}

new Block_Registration();
