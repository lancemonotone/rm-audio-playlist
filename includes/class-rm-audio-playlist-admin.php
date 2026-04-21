<?php
/**
 * Admin scripts (ACF edit screen).
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Admin
 */
class RM_Audio_Playlist_Admin {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'acf/input/admin_enqueue_scripts', array( self::class, 'enqueue_acf_scripts' ) );
		add_action( 'acf/save_post', array( self::class, 'fill_empty_track_titles_on_save' ), 20 );
		add_action(
			'acf/render_field/key=' . RM_Audio_Playlist_Acf::SHORTCODE_PANEL_FIELD_KEY,
			array( self::class, 'render_shortcode_panel' ),
			1
		);
	}

	/**
	 * After ACF saves, set empty track titles from the MP3 attachment filename (stem only).
	 *
	 * @param int|string $post_id Post ID or 'options'.
	 */
	public static function fill_empty_track_titles_on_save( $post_id ): void {
		if ( ! is_numeric( $post_id ) ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		if ( RM_Audio_Playlist_Cpt::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_sub_field' ) ) {
			return;
		}

		static $running = false;
		if ( $running ) {
			return;
		}

		$rows = get_field( RM_Audio_Playlist_Acf::REPEATER, $post_id );
		if ( ! is_array( $rows ) || $rows === array() ) {
			return;
		}

		$running = true;
		try {
			$row_num = 1;
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					++$row_num;
					continue;
				}
				$file_raw = $row[ RM_Audio_Playlist_Acf::FILE_KEY ] ?? null;
				if ( is_array( $file_raw ) && isset( $file_raw['ID'] ) ) {
					$file_id = (int) $file_raw['ID'];
				} elseif ( is_numeric( $file_raw ) ) {
					$file_id = (int) $file_raw;
				} else {
					$file_id = 0;
				}
				$title = isset( $row[ RM_Audio_Playlist_Acf::TITLE_KEY ] ) ? trim( (string) $row[ RM_Audio_Playlist_Acf::TITLE_KEY ] ) : '';
				if ( $file_id <= 0 || '' !== $title ) {
					++$row_num;
					continue;
				}
				$stem = self::attachment_filename_stem( $file_id );
				if ( '' === $stem ) {
					++$row_num;
					continue;
				}
				update_sub_field(
					array(
						RM_Audio_Playlist_Acf::REPEATER,
						$row_num,
						RM_Audio_Playlist_Acf::TITLE_KEY,
					),
					$stem,
					$post_id
				);
				++$row_num;
			}
		} finally {
			$running = false;
		}
	}

	/**
	 * Filename without extension from attachment (separators → spaces), or attachment post title.
	 */
	private static function attachment_filename_stem( int $file_id ): string {
		$path = get_attached_file( $file_id );
		if ( is_string( $path ) && $path !== '' ) {
			$base = wp_basename( $path );
			$stem = preg_replace( '/\.[^.]+\z/', '', $base );
			if ( ! is_string( $stem ) ) {
				return '';
			}
			return self::normalize_track_title_from_filename_stem( $stem );
		}
		$post = get_post( $file_id );
		if ( $post instanceof \WP_Post && $post->post_title !== '' ) {
			return trim( (string) $post->post_title );
		}
		return '';
	}

	/**
	 * Turn filename stem into a readable title: separators → spaces, collapse whitespace.
	 */
	private static function normalize_track_title_from_filename_stem( string $stem ): string {
		// Hyphens, Unicode dashes, underscores, dots, pipe, middle dot, bullets, plus (common in tags).
		$stem = preg_replace( '/[\s_.|·•+]+|\p{Pd}+/u', ' ', $stem );
		if ( ! is_string( $stem ) ) {
			return '';
		}
		$stem = preg_replace( '/\s+/', ' ', $stem );
		return trim( $stem );
	}

	/**
	 * Output copyable shortcode (first field in the group) when the playlist is published.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 */
	public static function render_shortcode_panel( array $field ): void {
		unset( $field );
		global $post;
		if ( ! $post instanceof \WP_Post || RM_Audio_Playlist_Cpt::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
			?>
			<p class="description" style="margin: 0;">
				<?php esc_html_e( 'Publish or schedule this playlist to copy the embed shortcode.', 'rm-audio-playlist' ); ?>
			</p>
			<?php
			return;
		}
		$post_id = (int) $post->ID;
		if ( $post_id <= 0 ) {
			return;
		}

		$code = sprintf( '[rm_audio_playlist id="%d"]', $post_id );
		?>
		<div class="rm-pl-shortcode-panel notice notice-info inline" style="margin: 0 0 16px; padding: 10px 12px;">
			<p class="description" style="margin: 0 0 8px;">
				<?php esc_html_e( 'Copy this into a page, post, or HTML block to embed this playlist.', 'rm-audio-playlist' ); ?>
			</p>
			<label class="screen-reader-text" for="rm-pl-shortcode-copy"><?php esc_html_e( 'Playlist shortcode', 'rm-audio-playlist' ); ?></label>
			<input
				id="rm-pl-shortcode-copy"
				type="text"
				readonly
				class="large-text code"
				style="width: 100%; max-width: 40rem; font-size: 13px;"
				value="<?php echo esc_attr( $code ); ?>"
			/>
		</div>
		<?php
	}

	/**
	 * Register script for playlist edit screen (ACF track fields).
	 */
	public static function enqueue_acf_scripts(): void {
		$screen = get_current_screen();
		if ( ! $screen || RM_Audio_Playlist_Cpt::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'rm-audio-playlist-admin',
			RM_AUDIO_PLAYLIST_URL . 'assets/js/rm-audio-playlist-admin.js',
			array(),
			RM_AUDIO_PLAYLIST_VERSION,
			true
		);
	}
}
