<?php
/**
 * Loads ACF field groups from each block folder (fields.json + acf-json).
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Registers local field groups from JSON under blocks/ (rm-blocks pattern).
 */
final class Acf {

	/**
	 * @var array<string, true>
	 */
	private array $registered_groups = array();

	public function __construct() {
		add_action( 'acf/init', array( $this, 'register_field_groups' ), 5 );
	}

	public function register_field_groups(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$blocks_dir = RM_AUDIO_PLAYLIST_DIR . 'blocks';
		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_dirs = glob( $blocks_dir . '/*', GLOB_ONLYDIR );
		if ( ! is_array( $block_dirs ) ) {
			return;
		}

		foreach ( $block_dirs as $block_dir ) {
			$this->register_block_folder_fields( $block_dir );
		}
	}

	/**
	 * Load fields.json and optional acf-json/group_*.json for one block folder.
	 */
	private function register_block_folder_fields( string $block_dir ): void {
		$acf_dir = $block_dir . '/acf-json';
		if ( is_dir( $acf_dir ) ) {
			$files = glob( $acf_dir . '/group_*.json' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$this->register_field_group_file( $file );
				}
			}
		}

		$fields_file = $block_dir . '/fields.json';
		$this->register_field_group_file(
			$fields_file,
			'group_' . sanitize_title( basename( $block_dir ) )
		);
	}

	/**
	 * @param string $fields_file Absolute path to a field group JSON file.
	 * @param string $fallback_key Used when JSON omits `key`.
	 */
	private function register_field_group_file( string $fields_file, string $fallback_key = '' ): void {
		if ( ! is_file( $fields_file ) ) {
			return;
		}

		$raw = file_get_contents( $fields_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return;
		}

		/** @var array<string, mixed>|null $fields_config */
		$fields_config = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $fields_config ) ) {
			return;
		}

		if ( empty( $fields_config['key'] ) ) {
			if ( '' === $fallback_key ) {
				$fallback_key = 'group_' . sanitize_title( basename( $fields_file, '.json' ) );
			}
			$fields_config['key'] = $fallback_key;
		}

		$key = (string) $fields_config['key'];
		if ( isset( $this->registered_groups[ $key ] ) ) {
			return;
		}

		acf_add_local_field_group( $fields_config );
		$this->registered_groups[ $key ] = true;
	}
}

new Acf();
