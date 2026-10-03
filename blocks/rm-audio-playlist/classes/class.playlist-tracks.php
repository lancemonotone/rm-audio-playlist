<?php
/**
 * Playlist track-row domain ops (clear files, bulk downloadable).
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Mutates the tracks repeater and playlist-scoped attachments.
 */
final class Playlist_Tracks {

	public function __construct() {
		add_filter( 'rm_audio_playlist_clear_tracks', array( self::class, 'filter_clear_tracks' ), 10, 2 );
		add_filter( 'rm_audio_playlist_set_downloadable_all', array( self::class, 'filter_set_downloadable_all' ), 10, 3 );
		add_filter( 'rm_audio_playlist_downloadable_bulk_state', array( self::class, 'filter_downloadable_bulk_state' ), 10, 2 );
	}

	/**
	 * @param mixed $result Prior filter value.
	 * @return array{deleted: int[], skipped: int[], errors: string[]}|mixed
	 */
	public static function filter_clear_tracks( $result, int $post_id ) {
		if ( null !== $result ) {
			return $result;
		}
		return self::clear( $post_id );
	}

	/**
	 * @param mixed $result Prior filter value.
	 * @return array{updated: int, downloadable: bool}|mixed
	 */
	public static function filter_set_downloadable_all( $result, int $post_id, bool $downloadable ) {
		if ( null !== $result ) {
			return $result;
		}
		return self::set_downloadable_all( $post_id, $downloadable );
	}

	/**
	 * @param mixed $result Prior filter value.
	 * @return 'all'|'none'|'mixed'|'empty'|mixed
	 */
	public static function filter_downloadable_bulk_state( $result, int $post_id ) {
		if ( null !== $result ) {
			return $result;
		}
		return self::downloadable_bulk_state( $post_id );
	}

	/**
	 * Delete playlist-scoped MP3 attachments and empty the tracks repeater.
	 *
	 * @return array{deleted: int[], skipped: int[], errors: string[]}
	 */
	public static function clear( int $post_id ): array {
		$deleted = array();
		$skipped = array();
		$errors  = array();

		$rows = get_field( Constants::REPEATER, $post_id );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$file_ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$file_id = self::attachment_id_from_repeater_file_value( $row[ Constants::FILE_KEY ] ?? null );
			if ( $file_id > 0 ) {
				$file_ids[ $file_id ] = true;
			}
		}

		foreach ( array_keys( $file_ids ) as $file_id ) {
			if ( ! self::attachment_is_in_playlist_upload_dir( $file_id, $post_id ) ) {
				$skipped[] = $file_id;
				continue;
			}
			$ok = wp_delete_attachment( $file_id, true );
			if ( $ok ) {
				$deleted[] = $file_id;
			} else {
				$errors[] = sprintf(
					/* translators: %d: attachment ID */
					__( 'Could not delete attachment %d.', 'rm-audio-playlist' ),
					$file_id
				);
			}
		}

		update_field( Constants::REPEATER, array(), $post_id );

		return array(
			'deleted' => $deleted,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Set Allow download on every track row that has an MP3.
	 *
	 * @return array{updated: int, downloadable: bool}
	 */
	public static function set_downloadable_all( int $post_id, bool $downloadable ): array {
		$rows = get_field( Constants::REPEATER, $post_id );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$value   = $downloadable ? 1 : 0;
		$updated = 0;

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$file_id = self::attachment_id_from_repeater_file_value( $row[ Constants::FILE_KEY ] ?? null );
			if ( $file_id <= 0 ) {
				continue;
			}
			$rows[ $index ][ Constants::DOWNLOADABLE_KEY ] = $value;
			++$updated;
		}

		if ( $updated > 0 ) {
			update_field( Constants::REPEATER, $rows, $post_id );
		}

		return array(
			'updated'      => $updated,
			'downloadable' => $downloadable,
		);
	}

	/**
	 * Bulk download toggle state for rows that have an MP3.
	 *
	 * @return 'all'|'none'|'mixed'|'empty'
	 */
	public static function downloadable_bulk_state( int $post_id ): string {
		$rows = get_field( Constants::REPEATER, $post_id );
		if ( ! is_array( $rows ) || array() === $rows ) {
			return 'empty';
		}

		$with_file = 0;
		$on        = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$file_id = self::attachment_id_from_repeater_file_value( $row[ Constants::FILE_KEY ] ?? null );
			if ( $file_id <= 0 ) {
				continue;
			}
			++$with_file;
			if ( ! empty( $row[ Constants::DOWNLOADABLE_KEY ] ) ) {
				++$on;
			}
		}

		if ( 0 === $with_file ) {
			return 'empty';
		}
		if ( 0 === $on ) {
			return 'none';
		}
		if ( $on === $with_file ) {
			return 'all';
		}
		return 'mixed';
	}

	/**
	 * True when the attachment file lives under uploads/rm-audio-playlist/{playlist ID}/.
	 */
	private static function attachment_is_in_playlist_upload_dir( int $file_id, int $playlist_id ): bool {
		$path = get_attached_file( $file_id );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}
		$prefix = trailingslashit( wp_normalize_path( $uploads['basedir'] ) )
			. Upload_Dir::SUBDIR
			. '/'
			. (string) $playlist_id
			. '/';
		return str_starts_with( wp_normalize_path( $path ), $prefix );
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

new Playlist_Tracks();
