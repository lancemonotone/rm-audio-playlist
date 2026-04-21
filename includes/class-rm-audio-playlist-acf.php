<?php
/**
 * Local ACF field group: ordered tracks.
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Acf
 */
class RM_Audio_Playlist_Acf {

	public const REPEATER   = 'rm_pl_tracks';
	public const FILE_KEY   = 'rm_pl_file';
	public const TITLE_KEY       = 'rm_pl_track_title';
	public const DOWNLOADABLE_KEY = 'rm_pl_downloadable';
	public const ARTWORK_KEY     = 'rm_pl_artwork';
	/** ACF message field: UI only, no stored value (shortcode panel rendered via acf/render_field). */
	public const SHORTCODE_PANEL_FIELD_KEY = 'field_rm_pl_shortcode_panel';
	public const KEY_PREFIX = 'group_rm_pl_';

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
						'key'           => self::SHORTCODE_PANEL_FIELD_KEY,
						'label'         => __( 'Shortcode', 'rm-audio-playlist' ),
						'name'          => '',
						'type'          => 'message',
						'message'       => '',
						'new_lines'     => '',
						'esc_html'      => 0,
					),
					array(
						'key'           => 'field_rm_pl_artwork',
						'label'         => __( 'Playlist artwork', 'rm-audio-playlist' ),
						'name'          => self::ARTWORK_KEY,
						'type'          => 'image',
						'instructions'  => __( 'Square cover image shown on the public player (optional).', 'rm-audio-playlist' ),
						'required'      => 0,
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
						'mime_types'    => 'jpg,jpeg,png,webp,gif',
					),
					array(
						'key'          => 'field_rm_pl_tracks',
						'label'        => __( 'Tracks', 'rm-audio-playlist' ),
						'name'         => self::REPEATER,
						'type'         => 'repeater',
						'instructions' => __( 'Add files in play order. Only MP3 files are allowed in the uploader (plugin whitelist).', 'rm-audio-playlist' ),
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
								'instructions' => __( 'Overrides the attachment title on the public player. If left empty, it is filled from the MP3 filename when you save.', 'rm-audio-playlist' ),
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
							'value'    => RM_Audio_Playlist_Cpt::POST_TYPE,
						),
					),
				),
				'position' => 'normal',
			)
		);
	}
}
