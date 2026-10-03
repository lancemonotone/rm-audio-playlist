<?php
/**
 * Public player markup for one playlist instance.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Renders player shell; payload via filter.
 */
final class Player_Render {

	public function __construct() {
		add_filter( 'rm_audio_playlist_render_player', array( self::class, 'filter_render' ), 10, 3 );
	}

	/**
	 * @param string $html        Prior markup.
	 * @param int    $id          Playlist post ID.
	 * @param string $extra_class Extra CSS classes.
	 */
	public static function filter_render( string $html, int $id, string $extra_class = '' ): string {
		if ( '' !== $html ) {
			return $html;
		}
		return self::render( $id, $extra_class );
	}

	/**
	 * Markup for one player instance.
	 *
	 * @param int    $id          Playlist post ID.
	 * @param string $extra_class Extra CSS classes (sanitized as attribute).
	 */
	public static function render( int $id, string $extra_class = '' ): string {
		if ( $id <= 0 ) {
			return '';
		}
		$payload = apply_filters( 'rm_audio_playlist_playlist_payload', null, $id );
		if ( is_wp_error( $payload ) || ! is_array( $payload ) || empty( $payload['tracks'] ) ) {
			if ( is_user_logged_in() && current_user_can( 'edit_post', $id ) ) {
				$msg = is_wp_error( $payload )
					? $payload->get_error_message()
					: __( 'Add at least one MP3 in the tracks repeater.', 'rm-audio-playlist' );
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

new Player_Render();
