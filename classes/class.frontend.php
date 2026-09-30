<?php
/**
 * Playlist payload + player markup (used by the ACF block template).
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Public playlist markup/payload helpers.
 */
final class Frontend {

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
	public static function get_playlist_payload( int $post_id ) {
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
			$artwork_id = (int) get_field( Acf::ARTWORK_KEY, $post_id );
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

		$rows = function_exists( 'get_field' ) ? get_field( Acf::REPEATER, $post_id ) : null;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$file_id      = is_array( $row ) && isset( $row[ Acf::FILE_KEY ] ) ? (int) $row[ Acf::FILE_KEY ] : 0;
				$override     = is_array( $row ) && ! empty( $row[ Acf::TITLE_KEY ] ) ? (string) $row[ Acf::TITLE_KEY ] : '';
				$downloadable = is_array( $row ) && ! empty( $row[ Acf::DOWNLOADABLE_KEY ] );
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

	/**
	 * Markup for one player instance (ACF block template).
	 *
	 * @param int    $id          Playlist post ID.
	 * @param string $extra_class Extra CSS classes (sanitized as attribute).
	 */
	public static function render( int $id, string $extra_class = '' ): string {
		if ( $id <= 0 ) {
			return '';
		}
		$payload = self::get_playlist_payload( $id );
		if ( is_wp_error( $payload ) || empty( $payload['tracks'] ) ) {
			if ( is_user_logged_in() && current_user_can( 'edit_post', $id ) ) {
				$msg = is_wp_error( $payload ) ? $payload->get_error_message() : __( 'Add at least one MP3 in the tracks repeater.', 'rm-audio-playlist' );
				return '<p class="rm-audio-playlist--error">' . esc_html( $msg ) . '</p>';
			}
			return '';
		}
		$json = wp_json_encode(
			$payload,
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		$uid = 'rm-pl-' . $id . '-' . (string) wp_unique_id( 'a' );
		$cls = 'rm-audio-playlist' . ( '' !== $extra_class ? ' ' . esc_attr( $extra_class ) : '' );
		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $cls ); ?>"
			id="<?php echo esc_attr( $uid ); ?>"
			data-rm-playlist="<?php echo esc_attr( (string) $json ); ?>"
		>
			<div class="rm-audio-playlist__noscript">
				<p><strong><?php echo esc_html( $payload['title'] ); ?></strong></p>
				<ol>
					<?php foreach ( $payload['tracks'] as $t ) : ?>
					<li><a href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ol>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
