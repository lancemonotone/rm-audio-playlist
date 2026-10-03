<?php
/**
 * Fill empty track titles on playlist save (ID3, then filename stem).
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * ACF save_post title autofill for track rows.
 */
final class Track_Titles {

	public function __construct() {
		add_action( 'acf/save_post', array( self::class, 'fill_empty_track_titles_on_save' ), 20 );
	}

	/**
	 * After ACF saves, set empty track titles from embedded ID3 tags when readable, else the file stem.
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
		if ( Cpt::POST_TYPE !== get_post_type( $post_id ) ) {
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

		$rows = get_field( Constants::REPEATER, $post_id );
		if ( ! is_array( $rows ) || array() === $rows ) {
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
				$file_id = self::attachment_id_from_repeater_file_value( $row[ Constants::FILE_KEY ] ?? null );
				$title   = isset( $row[ Constants::TITLE_KEY ] ) ? trim( (string) $row[ Constants::TITLE_KEY ] ) : '';
				if ( $file_id <= 0 || '' !== $title ) {
					++$row_num;
					continue;
				}
				$suggested = self::suggested_track_title_for_attachment( $file_id );
				if ( '' === $suggested ) {
					++$row_num;
					continue;
				}
				update_sub_field(
					array(
						Constants::REPEATER,
						$row_num,
						Constants::TITLE_KEY,
					),
					$suggested,
					$post_id
				);
				++$row_num;
			}
		} finally {
			$running = false;
		}
	}

	/**
	 * Default label for an empty track title: ID3 artist/title when present, else filename stem.
	 */
	private static function suggested_track_title_for_attachment( int $file_id ): string {
		$from_tags = self::track_title_from_id3_tags( $file_id );
		if ( '' !== $from_tags ) {
			return $from_tags;
		}
		return self::attachment_filename_stem( $file_id );
	}

	/**
	 * Build a display title from WordPress audio metadata (getID3): "Artist - Title", title-only, or artist-only.
	 *
	 * @return string Empty if no usable tag text (caller falls back to filename stem).
	 */
	private static function track_title_from_id3_tags( int $file_id ): string {
		if ( ! function_exists( 'wp_read_audio_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$path = get_attached_file( $file_id );
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		$meta = wp_read_audio_metadata( $path );
		if ( ! is_array( $meta ) ) {
			return '';
		}
		$artist = '';
		if ( isset( $meta['artist'] ) && is_string( $meta['artist'] ) ) {
			$artist = self::normalize_track_label_piece( $meta['artist'] );
		}
		if ( '' === $artist && isset( $meta['band'] ) && is_string( $meta['band'] ) ) {
			$artist = self::normalize_track_label_piece( $meta['band'] );
		}
		$title = isset( $meta['title'] ) && is_string( $meta['title'] )
			? self::normalize_track_label_piece( $meta['title'] )
			: '';
		if ( '' !== $artist && '' !== $title ) {
			return $artist . ' - ' . $title;
		}
		if ( '' !== $title ) {
			return $title;
		}
		if ( '' !== $artist ) {
			return $artist;
		}
		return '';
	}

	/**
	 * Single-line text from tag values for storage/display.
	 */
	private static function normalize_track_label_piece( string $raw ): string {
		$s = wp_strip_all_tags( $raw );
		$s = wp_specialchars_decode( $s, ENT_QUOTES );
		$s = preg_replace( '/\s+/u', ' ', $s );
		if ( ! is_string( $s ) ) {
			return '';
		}
		return trim( $s );
	}

	/**
	 * Filename without extension from attachment (separators → spaces), or attachment post title.
	 */
	private static function attachment_filename_stem( int $file_id ): string {
		$path = get_attached_file( $file_id );
		if ( is_string( $path ) && '' !== $path ) {
			$base = wp_basename( $path );
			$stem = preg_replace( '/\.[^.]+\z/', '', $base );
			if ( ! is_string( $stem ) ) {
				return '';
			}
			return self::normalize_track_title_from_filename_stem( $stem );
		}
		$post = get_post( $file_id );
		if ( $post instanceof \WP_Post && '' !== $post->post_title ) {
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
	 * @param mixed $file_raw ACF file subfield value.
	 */
	private static function attachment_id_from_repeater_file_value( $file_raw ): int {
		if ( is_array( $file_raw ) && isset( $file_raw['ID'] ) ) {
			return (int) $file_raw['ID'];
		}
		if ( is_numeric( $file_raw ) ) {
			return (int) $file_raw;
		}
		return 0;
	}
}

new Track_Titles();
