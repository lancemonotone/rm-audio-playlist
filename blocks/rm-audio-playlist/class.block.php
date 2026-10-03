<?php
/**
 * Block wrapper helpers for acf/rm-audio-playlist.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Playlist block field name and section wrapper.
 */
final class Playlist_Block {

	public const FIELD_NAME = 'rm_pl_block_playlist';

	/**
	 * Open section landmark with layout class + block supports (anchor / className).
	 *
	 * @param array<string, mixed> $block Block instance.
	 */
	public static function open_shell( array $block ): void {
		$classes = array(
			'audio',
		);

		if ( ! empty( $block['className'] ) ) {
			foreach ( array_filter( explode( ' ', (string) $block['className'] ) ) as $cn ) {
				$classes[] = sanitize_html_class( $cn );
			}
		}

		$class_string = implode( ' ', array_map( 'sanitize_html_class', $classes ) );

		// get_block_wrapper_attributes() requires WP_Block_Supports::$block_to_render.
		// ACF editor preview can run templates without that context.
		if ( is_array( \WP_Block_Supports::$block_to_render ) ) {
			$wrapper_attrs = get_block_wrapper_attributes(
				array(
					'class' => $class_string,
				)
			);
		} else {
			$parts = array( 'class="' . esc_attr( $class_string ) . '"' );
			if ( ! empty( $block['anchor'] ) ) {
				$parts[] = 'id="' . esc_attr( (string) $block['anchor'] ) . '"';
			}
			$wrapper_attrs = implode( ' ', $parts );
		}

		echo '<section ' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
