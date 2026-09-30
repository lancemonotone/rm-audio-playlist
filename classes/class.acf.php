<?php
/**
 * Local ACF field group: ordered tracks.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Playlist CPT ACF fields.
 */
final class Acf {

	public const REPEATER         = 'rm_pl_tracks';
	public const FILE_KEY         = 'rm_pl_file';
	public const TITLE_KEY        = 'rm_pl_track_title';
	public const DOWNLOADABLE_KEY = 'rm_pl_downloadable';
	public const ARTWORK_KEY      = 'rm_pl_artwork';
	/** ACF message fields: UI only (rendered via acf/render_field), one row of columns. */
	public const CLEAR_TRACKS_DESC_FIELD_KEY   = 'field_rm_pl_clear_tracks_desc';
	public const CLEAR_TRACKS_ACTION_FIELD_KEY = 'field_rm_pl_clear_tracks_action';
	public const DOWNLOAD_ALL_FIELD_KEY        = 'field_rm_pl_download_all';
	public const KEY_PREFIX                    = 'group_rm_pl_';

	public function __construct() {
		add_action( 'acf/init', array( self::class, 'register' ) );
	}

	/**
	 * Register field group (ACF 5+).
	 */
	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		/**
		 * File field: MP3 only (whitelist).
		 * Return format: attachment ID.
		 */
		acf_add_local_field_group(
			array(
				'key'      => 'group_rm_audio_playlist',
				'title'    => __( 'Playlist tracks', 'rm-audio-playlist' ),
				'fields'   => array(
					array(
						'key'           => 'field_rm_pl_artwork',
						'label'         => __( 'Playlist artwork', 'rm-audio-playlist' ),
						'name'          => self::ARTWORK_KEY,
						'type'          => 'image',
						'instructions'  => sprintf(
							/* translators: %s: folder basename, e.g. rm-audio-playlist. */
							__( 'Square cover image for the public player (optional). New uploads from this screen are stored under wp-content/uploads/%s/{playlist ID}/ alongside tracks.', 'rm-audio-playlist' ),
							Upload_Dir::SUBDIR
						),
						'required'      => 0,
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
						'mime_types'    => 'jpg,jpeg,png,webp,gif',
					),
					array(
						'key'       => self::CLEAR_TRACKS_DESC_FIELD_KEY,
						'label'     => __( 'Reset tracks', 'rm-audio-playlist' ),
						'name'      => '',
						'type'      => 'message',
						'message'   => '',
						'new_lines' => '',
						'esc_html'  => 0,
						'wrapper'   => array(
							'width' => '50',
							'class' => 'rm-pl-toolbar-col rm-pl-toolbar-col--desc',
						),
					),
					array(
						'key'       => self::CLEAR_TRACKS_ACTION_FIELD_KEY,
						'label'     => __( 'Remove MP3s', 'rm-audio-playlist' ),
						'name'      => '',
						'type'      => 'message',
						'message'   => '',
						'new_lines' => '',
						'esc_html'  => 0,
						'wrapper'   => array(
							'width' => '25',
							'class' => 'rm-pl-toolbar-col rm-pl-toolbar-col--clear',
						),
					),
					array(
						'key'       => self::DOWNLOAD_ALL_FIELD_KEY,
						'label'     => __( 'Allow download all', 'rm-audio-playlist' ),
						'name'      => '',
						'type'      => 'message',
						'message'   => '',
						'new_lines' => '',
						'esc_html'  => 0,
						'wrapper'   => array(
							'width' => '25',
							'class' => 'rm-pl-toolbar-col rm-pl-toolbar-col--download',
						),
					),
					array(
						'key'          => 'field_rm_pl_tracks',
						'label'        => __( 'Tracks', 'rm-audio-playlist' ),
						'name'         => self::REPEATER,
						'type'         => 'repeater',
						'instructions' => sprintf(
							/* translators: %s: folder basename, e.g. rm-audio-playlist. */
							__( 'Add files in play order. New MP3 uploads from this playlist screen are stored under wp-content/uploads/%s/{playlist ID}/ (no dated folders). Uploads from other screens are unchanged. Only MP3 files are allowed (plugin whitelist).', 'rm-audio-playlist' ),
							Upload_Dir::SUBDIR
						),
						'required'     => 0,
						'layout'       => 'row',
						'button_label' => __( 'Add track', 'rm-audio-playlist' ),
						'sub_fields'   => array(
							array(
								'key'           => 'field_rm_pl_file',
								'label'         => __( 'MP3 file', 'rm-audio-playlist' ),
								'name'          => self::FILE_KEY,
								'type'          => 'file',
								'required'      => 1,
								'return_format' => 'id',
								'library'       => 'all',
								'mime_types'    => 'mp3',
							),
							array(
								'key'          => 'field_rm_pl_track_title',
								'label'        => __( 'Track title (optional)', 'rm-audio-playlist' ),
								'name'         => self::TITLE_KEY,
								'type'         => 'text',
								'required'     => 0,
								'instructions' => __( 'Overrides the attachment title on the public player. If left empty, it is filled on save from embedded MP3 tags when available (Artist - Title if both exist), otherwise from the filename.', 'rm-audio-playlist' ),
							),
							array(
								'key'           => 'field_rm_pl_downloadable',
								'label'         => __( 'Allow download', 'rm-audio-playlist' ),
								'name'          => self::DOWNLOADABLE_KEY,
								'type'          => 'true_false',
								'instructions'  => __( 'When enabled, the public player shows a download control for this track.', 'rm-audio-playlist' ),
								'required'      => 0,
								'default_value' => 0,
								'ui'            => 1,
								'ui_on_text'    => __( 'Yes', 'rm-audio-playlist' ),
								'ui_off_text'   => __( 'No', 'rm-audio-playlist' ),
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => Cpt::POST_TYPE,
						),
					),
				),
				'position' => 'normal',
			)
		);
	}
}

new Acf();
