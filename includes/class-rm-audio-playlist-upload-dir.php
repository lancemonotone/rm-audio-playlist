<?php
/**
 * Store playlist MP3 and artwork uploads under wp-content/uploads/rm-audio-playlist/{post ID}/.
 *
 * The plugin does not alter stored filenames; WordPress core still runs sanitize_file_name()
 * and wp_unique_filename() on upload (security / collisions).
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Upload_Dir
 */
class RM_Audio_Playlist_Upload_Dir {

	public const SUBDIR = 'rm-audio-playlist';

	/**
	 * Image extensions allowed for playlist artwork (matches ACF artwork field).
	 *
	 * @var string[]
	 */
	private const ARTWORK_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );

	/**
	 * Register filter.
	 */
	public static function init(): void {
		add_filter( 'upload_dir', array( self::class, 'filter_upload_dir' ), 99 );
	}

	/**
	 * Create top-level folder on activation.
	 */
	public static function ensure_base_folder(): void {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}
		$base = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
		}
		$index = $base . '/index.php';
		if ( ! is_file( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny stub on activation only.
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * @param array<string, string|bool> $uploads Upload dir array from wp_upload_dir().
	 * @return array<string, string|bool>
	 */
	public static function filter_upload_dir( array $uploads ): array {
		if ( ! empty( $uploads['error'] ) ) {
			return $uploads;
		}

		if ( empty( $_FILES ) || ! is_array( $_FILES ) ) {
			return $uploads;
		}

		if ( ! self::request_upload_is_playlist_asset() ) {
			return $uploads;
		}

		$post_id = self::resolve_upload_parent_post_id();
		if ( $post_id <= 0 ) {
			return $uploads;
		}

		if ( RM_Audio_Playlist_Cpt::POST_TYPE !== get_post_type( $post_id ) ) {
			return $uploads;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $uploads;
		}

		$subdir            = '/' . self::SUBDIR . '/' . (string) $post_id;
		$uploads['subdir'] = $subdir;
		$uploads['path']   = $uploads['basedir'] . $subdir;
		$uploads['url']    = $uploads['baseurl'] . $subdir;

		return $uploads;
	}

	/**
	 * Parent post for the in-progress upload (classic async upload, REST media, etc.).
	 */
	private static function resolve_upload_parent_post_id(): int {
		if ( isset( $_REQUEST['post_id'] ) ) {
			return absint( wp_unslash( $_REQUEST['post_id'] ) );
		}
		if ( isset( $_POST['post_id'] ) ) {
			return absint( wp_unslash( $_POST['post_id'] ) );
		}
		if ( isset( $_POST['post'] ) ) {
			return absint( wp_unslash( $_POST['post'] ) );
		}
		return 0;
	}

	/**
	 * MP3 (tracks) or image types allowed for playlist artwork.
	 */
	private static function request_upload_is_playlist_asset(): bool {
		foreach ( $_FILES as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$name = $file['name'] ?? '';
			if ( ! is_string( $name ) || $name === '' ) {
				continue;
			}
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( $ext === 'mp3' ) {
				return true;
			}
			foreach ( self::ARTWORK_EXTENSIONS as $allowed ) {
				if ( $ext === $allowed ) {
					return true;
				}
			}
		}
		return false;
	}
}
