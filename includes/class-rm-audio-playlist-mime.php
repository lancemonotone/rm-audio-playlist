<?php
/**
 * Allow MP3 uploads when servers mis-detect type or mimes are restricted.
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Mime
 */
class RM_Audio_Playlist_Mime {

	/**
	 * Ensure MP3 is listed in allowed upload types.
	 *
	 * @param string[] $mimes Extension (or pipe list) => mime.
	 * @return string[]
	 */
	public static function allow_mp3( array $mimes ): array {
		$mimes['mp3'] = 'audio/mpeg';
		if ( ! isset( $mimes['mp3|m4a|m4b'] ) ) {
			$mimes['mp3|m4a|m4b'] = 'audio/mpeg';
		}
		return $mimes;
	}

	/**
	 * When the file is a .mp3, align extension and mime with audio/mpeg.
	 *
	 * @param string[]|array<string, string|false> $data File data from wp_check_filetype_and_ext.
	 * @param string                               $file Full path to temp file.
	 * @param string                               $filename Original filename.
	 * @param string[]|null                        $mimes      Mime map or null.
	 * @param string|false                         $real_mime  Detected mime.
	 * @return string[]|array<string, string|false>
	 */
	public static function fix_mp3_check( $data, $file, $filename, $mimes, $real_mime ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'mp3' !== $ext ) {
			return $data;
		}

		$allowed_reals = array(
			'audio/mpeg',
			'audio/mpeg3',
			'audio/x-mpeg-3',
			'audio/x-mpeg',
			'audio/mp3',
			'application/octet-stream',
			'application/x-download',
			'',
			false,
		);
		$is_known = in_array( $real_mime, $allowed_reals, true ) || ( is_string( $real_mime ) && 0 === strpos( $real_mime, 'audio/' ) );
		$probed   = self::is_probably_mp3( $file );

		if ( $is_known || $probed || false === $data['ext'] || ( isset( $data['type'] ) && false === $data['type'] ) ) {
			$data['ext']  = 'mp3';
			$data['type'] = 'audio/mpeg';
		}
		return $data;
	}

	/**
	 * @param string $file Absolute path to uploaded temp file.
	 */
	private static function is_probably_mp3( $file ): bool {
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return false;
		}
		$head = (string) file_get_contents( $file, false, null, 0, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( 0 === strncmp( $head, 'ID3', 3 ) ) {
			return true;
		}
		$fs = (string) file_get_contents( $file, false, null, 0, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return 0 === strncmp( $fs, "\xff\xfb", 2 ) || 0 === strncmp( $fs, "\xff\xf3", 2 ) || 0 === strncmp( $fs, "\xff\xf2", 2 );
	}
}
