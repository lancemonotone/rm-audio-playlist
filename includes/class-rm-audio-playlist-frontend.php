<?php
/**
 * Shortcode, asset enqueue, playlist payload.
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Frontend
 */
class RM_Audio_Playlist_Frontend {

	private const HANDLE_JS  = 'rm-audio-playlist-frontend';
	private const HANDLE_CSS = 'rm-audio-playlist-frontend';

	/**
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'register_assets' ) );
		add_shortcode( 'rm_audio_playlist', array( self::class, 'shortcode' ) );
	}

	/**
	 * Register script/style; actual enqueue happens when shortcode runs.
	 */
	public static function register_assets(): void {
		$base = trailingslashit( RM_AUDIO_PLAYLIST_URL );
		$ver  = RM_AUDIO_PLAYLIST_VERSION;

		wp_register_style( self::HANDLE_CSS, $base . 'assets/css/rm-audio-playlist-frontend.css', array(), $ver );
		wp_register_script( self::HANDLE_JS, $base . 'assets/js/rm-audio-playlist-frontend.js', array(), $ver, true );
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
		$text = wp_strip_all_tags( $text );
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
	 * @return array{title:string,tracks:array<int, array{url:string,title:string,downloadable?:bool,downloadName?:string}>,artworkUrl?:string,artworkAlt?:string}|\WP_Error
	 */
	public static function get_playlist_payload( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || RM_Audio_Playlist_Cpt::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'rm_pl_invalid', __( 'Invalid playlist.', 'rm-audio-playlist' ) );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error( 'rm_pl_private', __( 'This playlist is not available.', 'rm-audio-playlist' ) );
		}

		$title = self::plaintext_for_display( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
		$tracks = array();

		$artwork_url = '';
		$artwork_alt = '';
		if ( function_exists( 'get_field' ) ) {
			$artwork_id = (int) get_field( RM_Audio_Playlist_Acf::ARTWORK_KEY, $post_id );
			if ( $artwork_id > 0 && wp_attachment_is_image( $artwork_id ) ) {
				$img_url = wp_get_attachment_image_url( $artwork_id, 'medium' );
				if ( ! $img_url ) {
					$img_url = wp_get_attachment_url( $artwork_id );
				}
				if ( $img_url ) {
					$artwork_url = (string) $img_url;
					$artwork_alt = trim( self::plaintext_for_display( (string) get_post_meta( $artwork_id, '_wp_attachment_image_alt', true ) ) );
					if ( '' === $artwork_alt ) {
						$artwork_alt = $title;
					}
				}
			}
		}

		$rows = function_exists( 'get_field' ) ? get_field( RM_Audio_Playlist_Acf::REPEATER, $post_id ) : null;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$file_id  = is_array( $row ) && isset( $row[ RM_Audio_Playlist_Acf::FILE_KEY ] ) ? (int) $row[ RM_Audio_Playlist_Acf::FILE_KEY ] : 0;
				$override = is_array( $row ) && ! empty( $row[ RM_Audio_Playlist_Acf::TITLE_KEY ] ) ? (string) $row[ RM_Audio_Playlist_Acf::TITLE_KEY ] : '';
				$downloadable = is_array( $row ) && ! empty( $row[ RM_Audio_Playlist_Acf::DOWNLOADABLE_KEY ] );
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
		}
		return $out;
	}

	/**
	 * [rm_audio_playlist id="123" class="..."]
	 *
	 * @param string[]|array<string, string> $atts Shortcode atts.
	 */
	public static function shortcode( $atts ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'rm_audio_playlist'
		);
		$id   = (int) $atts['id'];
		$more = (string) $atts['class'];
		if ( $id <= 0 ) {
			if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
				return '<p class="rm-audio-playlist--error">' . esc_html__( 'Shortcode: set a valid id=" post ID ".', 'rm-audio-playlist' ) . '</p>';
			}
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
		if ( ! self::$assets_enqueued ) {
			wp_enqueue_style( self::HANDLE_CSS );
			wp_enqueue_script( self::HANDLE_JS );
			self::$assets_enqueued = true;
		}
		$json = wp_json_encode(
			$payload,
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		$uid  = 'rm-pl-' . $id . '-' . (string) wp_unique_id( 'a' );
		$cls  = 'rm-audio-playlist' . ( $more !== '' ? ' ' . esc_attr( $more ) : '' );
		ob_start();
		?>
		<div
			class="<?php echo esc_attr( $cls ); ?>"
			id="<?php echo esc_attr( $uid ); ?>"
			data-rm-playlist="<?php echo esc_attr( (string) $json ); ?>"
		>
			<?php
			// Minimal fallback if JS off; one track link at a time is not full UX but not empty.
			$first = $payload['tracks'][0];
			?>
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
