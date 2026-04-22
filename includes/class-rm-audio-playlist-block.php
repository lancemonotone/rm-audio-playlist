<?php

/**
 * ACF block: embed playlist in the block editor (requires ACF Pro).
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Block
 */
class RM_Audio_Playlist_Block {

	public const NAME       = 'rm-audio-playlist';
	public const FIELD_NAME = 'rm_pl_block_playlist';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action('acf/init', array(self::class, 'register'), 20);
	}

	/**
	 * Register block type and local field group.
	 */
	public static function register(): void {
		if (! function_exists('acf_register_block_type') || ! function_exists('acf_add_local_field_group')) {
			return;
		}

		self::register_field_group();

		acf_register_block_type(
			array(
				'name'              => self::NAME,
				'title'             => __('Audio playlist', 'rm-audio-playlist'),
				'description'       => __('RM Audio Playlist player — pick a playlist from the sidebar.', 'rm-audio-playlist'),
				'render_callback'   => array(self::class, 'render_block'),
				'category'          => 'media',
				'icon'              => 'playlist-audio',
				'keywords'          => array('audio', 'playlist', 'music', 'mp3', 'rm'),
				// ACF block API v2; use auto so the canvas shows preview by default and the field form when
				// the block is focused, with the toolbar control to switch preview ↔ edit.
				'acf_block_version' => 2,
				'mode'              => 'preview',
				'supports'          => array(
					'align'     => true,
					'anchor'    => true,
					'className' => true,
					'mode'      => true,
				),
				'enqueue_assets'    => array(self::class, 'enqueue_block_assets'),
			)
		);
	}

	/**
	 * Fields shown in the block sidebar.
	 */
	public static function register_field_group(): void {
		acf_add_local_field_group(
			array(
				'key'                   => 'group_rm_pl_block',
				'title'                 => __('Audio playlist player', 'rm-audio-playlist'),
				'fields'                => array(
					array(
						'key'           => 'field_rm_pl_block_playlist',
						'label'         => __('Playlist', 'rm-audio-playlist'),
						'name'          => self::FIELD_NAME,
						'type'          => 'post_object',
						'instructions'  => __('Choose a playlist from Audio playlists.', 'rm-audio-playlist'),
						'required'      => 0,
						'post_type'     => array(RM_Audio_Playlist_Cpt::POST_TYPE),
						'return_format' => 'id',
						'ui'            => 1,
						'allow_null'    => 1,
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/' . self::NAME,
						),
					),
				),
				'active'                => true,
				'show_in_rest'          => 0,
			)
		);
	}

	/**
	 * Load player assets in the block editor when the block is used.
	 */
	public static function enqueue_block_assets(): void {
		RM_Audio_Playlist_Frontend::enqueue_assets();
	}

	/**
	 * Block output (front end and editor preview).
	 *
	 * @param array<string, mixed> $block      Block settings.
	 * @param string               $content    Inner blocks / HTML.
	 * @param bool                 $is_preview Editor preview.
	 * @param int|string           $post_id    Post ID (may be 0 in site editor).
	 * @param mixed                $wp_block   WP_Block instance when provided by ACF (PHP 8+ passes extra args).
	 */
	public static function render_block($block, $content = '', $is_preview = false, $post_id = 0, $wp_block = null): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset($content, $post_id, $wp_block);
		$id = (int) get_field(self::FIELD_NAME);
		if ($id <= 0) {
			if ($is_preview) {
				echo '<p class="rm-audio-playlist--error">';
				esc_html_e('Select a playlist in the block sidebar.', 'rm-audio-playlist');
				echo '</p>';
			}
			return;
		}

		$extra = '';
		if (is_array($block) && ! empty($block['className'])) {
			$extra = (string) $block['className'];
		}

		echo RM_Audio_Playlist_Frontend::render($id, $extra); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with escaping in render().
	}
}
