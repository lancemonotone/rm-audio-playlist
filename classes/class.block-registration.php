<?php

/**
 * Discovers each blocks subfolder: registers the block type, field group JSON, and optional block class.
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

	/**
	 * @var array<string, true>
	 */
	private array $registered_groups = array();

	public function __construct() {
		add_action('acf/init', array($this, 'register_blocks'));
		add_filter('block_categories_all', array($this, 'register_block_categories'), 5, 2);
	}

	/**
	 * Put RM Audio Playlist first in the block inserter.
	 *
	 * @param array<int, array<string, mixed>> $block_categories     Categories.
	 * @param mixed                             $block_editor_context Editor context.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_categories(array $block_categories, $block_editor_context): array {
		unset($block_editor_context);
		array_unshift(
			$block_categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __('RM Audio Playlist', 'rm-audio-playlist'),
				'icon'  => null,
			)
		);
		return $block_categories;
	}

	public function register_blocks(): void {
		if (! function_exists('acf_add_local_field_group')) {
			return;
		}

		$blocks_dir = RM_AUDIO_PLAYLIST_DIR . 'blocks';
		if (! is_dir($blocks_dir)) {
			return;
		}

		$block_folders = glob($blocks_dir . '/*', GLOB_ONLYDIR);
		if (! is_array($block_folders)) {
			return;
		}

		foreach ($block_folders as $block_folder) {
			if (! is_file($block_folder . '/block.json')) {
				continue;
			}
			register_block_type($block_folder);
			$this->register_block_fields($block_folder);
			$this->load_block_class($block_folder);
		}
	}

	private function load_block_class(string $block_folder): void {
		$class_file = $block_folder . '/class.block.php';
		if (is_file($class_file)) {
			require_once $class_file;
		}
	}

	private function register_block_fields(string $block_folder): void {
		$fields_file = $block_folder . '/fields.json';
		if (! is_file($fields_file)) {
			return;
		}

		$raw = file_get_contents($fields_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if (false === $raw) {
			return;
		}

		/** @var array<string, mixed>|null $fields_config */
		$fields_config = json_decode($raw, true);
		if (JSON_ERROR_NONE !== json_last_error() || ! is_array($fields_config)) {
			return;
		}

		$key = isset($fields_config['key']) ? (string) $fields_config['key'] : 'group_' . sanitize_title(basename($block_folder));
		$fields_config['key'] = $key;

		if (isset($this->registered_groups[$key])) {
			return;
		}

		acf_add_local_field_group($fields_config);
		$this->registered_groups[$key] = true;
	}
}

new Block_Registration();
