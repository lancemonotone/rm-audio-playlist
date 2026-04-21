<?php
/**
 * Audio playlist post type.
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RM_Audio_Playlist_Cpt
 */
class RM_Audio_Playlist_Cpt {

	public const POST_TYPE = 'rm_audio_playlist';

	/**
	 * Register post type.
	 */
	public static function register(): void {
		$labels = array(
			'name'               => __( 'Audio playlists', 'rm-audio-playlist' ),
			'singular_name'      => __( 'Audio playlist', 'rm-audio-playlist' ),
			'add_new'            => __( 'Add New', 'rm-audio-playlist' ),
			'add_new_item'       => __( 'Add New Audio playlist', 'rm-audio-playlist' ),
			'edit_item'          => __( 'Edit Audio playlist', 'rm-audio-playlist' ),
			'new_item'           => __( 'New Audio playlist', 'rm-audio-playlist' ),
			'view_item'          => __( 'View Audio playlist', 'rm-audio-playlist' ),
			'search_items'       => __( 'Search Audio playlists', 'rm-audio-playlist' ),
			'not_found'          => __( 'No playlists found', 'rm-audio-playlist' ),
			'not_found_in_trash' => __( 'No playlists in trash', 'rm-audio-playlist' ),
			'menu_name'          => __( 'Audio playlists', 'rm-audio-playlist' ),
		);
		$args   = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'     => 26,
			'menu_icon'           => 'dashicons-playlist-audio',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
		);
		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Admin notice if ACF is missing.
	 */
	public static function admin_notice_acf(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( class_exists( 'ACF' ) || function_exists( 'acf' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'RM Audio Playlist requires Advanced Custom Fields (Pro recommended for the repeater field). Please activate ACF.', 'rm-audio-playlist' ); ?>
			</p>
		</div>
		<?php
	}
}
