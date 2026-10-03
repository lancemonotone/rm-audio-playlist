<?php
/**
 * JSON-safe playlist payload for the public player.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Builds track list + artwork data from a playlist post.
 */
final class Playlist_Payload {

	public function __construct() {
		add_filter( 'rm_audio_playlist_playlist_payload', array( self::class, 'filter_payload' ), 10, 2 );
	}

	/**
	 * @param mixed $result Prior filter value.
	 * @return array{title:string,tracks:array<int, array{url:string,title:string,downloadable?:bool,downloadName?:string}>,artworkUrl?:string,artworkThumbUrl?:string,artworkAlt?:string}|\WP_Error|mixed
	 */
	public static function filter_payload( $result, int $post_id ) {
		if ( null !== $result ) {
			return $result;
		}
		return self::get( $post_id );
	}

	/**
	 * Strip tags and decode HTML entities so titles show real characters (e.g. en dash) instead of &#8211;.
	 *
	 * Note: {@see wp_specialchars_decode()} only handles a small subset (amp, lt, quotes, etc.) and does not
	 * decode numeric entities like &#8211;; use html_entity_decode() with UTF-8.
	 *
	 * @param string $text Raw title from post/ACF.
	 */
	private static function plaintext_for_display( string $text ): string {
		$text  = wp_strip_all_tags( $text );
		$flags = ENT_QUOTES | ( defined( 'ENT_HTML5' ) ? ENT_HTML5 : 0 );
		$prev  = '';
		$out   = $text;
		$i     = 0;
		// Multi-pass for double-encoded strings (e.g. &amp;#8211;).
		while ( $out !== $prev && $i < 6 ) {
			$prev = $out;
			$out  = html_entity_decode( $out, $flags, 'UTF-8' );
			++$i;
		}
		return trim( $out );
	}

	/**
	 * Build JSON-safe track list for a playlist post.
	 *
	 * @return array{title:string,tracks:array<int, array{url:string,title:string,downloadable?:bool,downloadName?:string}>,artworkUrl?:string,artworkThumbUrl?:string,artworkAlt?:string}|\WP_Error
	 */
	public static function get( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || Cpt::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'rm_pl_invalid', __( 'Invalid playlist.', 'rm-audio-playlist' ) );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error( 'rm_pl_private', __( 'This playlist is not available.', 'rm-audio-playlist' ) );
		}

		$title  = self::plaintext_for_display( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
		$tracks = array();

		$artwork_url       = '';
		$artwork_thumb_url = '';
		$artwork_alt       = '';
		if ( function_exists( 'get_field' ) ) {
			$artwork_id = (int) get_field( Constants::ARTWORK_KEY, $post_id );
			if ( $artwork_id > 0 && wp_attachment_is_image( $artwork_id ) ) {
				$full_url  = wp_get_attachment_image_url( $artwork_id, 'full' );
				$thumb_url = wp_get_attachment_image_url( $artwork_id, 'medium' );
				if ( ! $full_url ) {
					$full_url = wp_get_attachment_url( $artwork_id );
				}
				if ( ! $thumb_url ) {
					$thumb_url = $full_url;
				}
				if ( $full_url ) {
					$artwork_url       = (string) $full_url;
					$artwork_thumb_url = (string) $thumb_url;
					$artwork_alt       = trim( self::plaintext_for_display( (string) get_post_meta( $artwork_id, '_wp_attachment_image_alt', true ) ) );
					if ( '' === $artwork_alt ) {
						$artwork_alt = $title;
					}
				}
			}
		}

		$rows = function_exists( 'get_field' ) ? get_field( Constants::REPEATER, $post_id ) : null;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$file_id      = is_array( $row ) && isset( $row[ Constants::FILE_KEY ] ) ? (int) $row[ Constants::FILE_KEY ] : 0;
				$override     = is_array( $row ) && ! empty( $row[ Constants::TITLE_KEY ] ) ? (string) $row[ Constants::TITLE_KEY ] : '';
				$downloadable = is_array( $row ) && ! empty( $row[ Constants::DOWNLOADABLE_KEY ] );
				if ( $file_id <= 0 ) {
					continue;
				}
				$mime = get_post_mime_type( $file_id );
				if ( $mime && 0 !== strpos( $mime, 'audio' ) && 'application/octet-stream' !== $mime ) {
					continue;
				}
				$url = wp_get_attachment_url( $file_id );
				if ( ! $url ) {
					continue;
				}
				$track_title = '' !== trim( $override ) ? self::plaintext_for_display( $override ) : '';
				if ( '' === $track_title ) {
					$raw_att_title = (string) get_post_field( 'post_title', $file_id, 'raw' );
					$track_title   = '' !== $raw_att_title
						? self::plaintext_for_display( $raw_att_title )
						: __( 'Untitled track', 'rm-audio-playlist' );
				}
				$entry = array(
					'url'   => $url,
					'title' => $track_title,
				);
				if ( $downloadable ) {
					$entry['downloadable'] = true;
					$fname                 = sanitize_file_name( $track_title . '.mp3' );
					if ( '' === $fname ) {
						$fname = 'track.mp3';
					}
					$entry['downloadName'] = $fname;
				}
				$tracks[] = $entry;
			}
		}

		$out = array(
			'title'  => (string) $title,
			'tracks' => $tracks,
		);
		if ( '' !== $artwork_url ) {
			$out['artworkUrl'] = $artwork_url;
			$out['artworkAlt'] = $artwork_alt;
			if ( '' !== $artwork_thumb_url && $artwork_thumb_url !== $artwork_url ) {
				$out['artworkThumbUrl'] = $artwork_thumb_url;
			}
		}
		return $out;
	}
}

new Playlist_Payload();
