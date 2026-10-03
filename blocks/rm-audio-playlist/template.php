<?php
/**
 * Audio playlist block (acf/rm-audio-playlist).
 *
 * @package Rm_Audio_Playlist
 *
 * @var array      $block
 * @var string     $content
 * @var bool       $is_preview
 * @var int|string $post_id
 */

declare(strict_types=1);

use Rm_Audio_Playlist\Playlist_Block;

unset( $content, $post_id );

$id = (int) get_field( Playlist_Block::FIELD_NAME );
if ( $id <= 0 ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p class="rm-audio-playlist--error">';
		esc_html_e( 'Select a playlist in the block sidebar.', 'rm-audio-playlist' );
		echo '</p>';
	}
	return;
}

$block = is_array( $block ) ? $block : array();
Playlist_Block::open_shell( $block );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with escaping in player render.
echo apply_filters( 'rm_audio_playlist_render_player', '', $id, '' );
echo '</section>';
