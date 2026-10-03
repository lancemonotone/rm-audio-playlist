<?php
/**
 * REST routes for playlist admin actions.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * HTTP surface only; domain work via filters.
 */
final class Rest {

	public function __construct() {
		add_action( 'rest_api_init', array( self::class, 'register_rest_routes' ) );
	}

	/**
	 * REST: clear playlist track files and set downloadable-all.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'rm-audio-playlist/v1',
			'/playlists/(?P<id>\d+)/clear-tracks',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_clear_playlist_tracks' ),
				'permission_callback' => array( self::class, 'rest_can_edit_playlist' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
				),
			)
		);
		register_rest_route(
			'rm-audio-playlist/v1',
			'/playlists/(?P<id>\d+)/set-downloadable-all',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_set_downloadable_all' ),
				'permission_callback' => array( self::class, 'rest_can_edit_playlist' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_can_edit_playlist( \WP_REST_Request $request ): bool {
		$post_id = (int) $request['id'];
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( Cpt::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_clear_playlist_tracks( \WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$error   = self::rest_preflight_playlist( $post_id, __( 'Save the playlist before removing tracks.', 'rm-audio-playlist' ) );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$result = apply_filters( 'rm_audio_playlist_clear_tracks', null, $post_id );
		if ( ! is_array( $result ) ) {
			return new \WP_Error(
				'rm_pl_clear_unavailable',
				__( 'Clear tracks handler is not available.', 'rm-audio-playlist' ),
				array( 'status' => 500 )
			);
		}
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_set_downloadable_all( \WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$error   = self::rest_preflight_playlist( $post_id, __( 'Save the playlist before changing download settings.', 'rm-audio-playlist' ) );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) || ! array_key_exists( 'downloadable', $params ) ) {
			return new \WP_Error(
				'rm_pl_invalid_request',
				__( 'Missing downloadable flag.', 'rm-audio-playlist' ),
				array( 'status' => 400 )
			);
		}

		$downloadable = (bool) $params['downloadable'];
		$result       = apply_filters( 'rm_audio_playlist_set_downloadable_all', null, $post_id, $downloadable );
		if ( ! is_array( $result ) ) {
			return new \WP_Error(
				'rm_pl_downloadable_unavailable',
				__( 'Download settings handler is not available.', 'rm-audio-playlist' ),
				array( 'status' => 500 )
			);
		}
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * @return null|\WP_Error Null when OK.
	 */
	private static function rest_preflight_playlist( int $post_id, string $not_saved_message ) {
		if ( wp_is_post_autosave( $post_id ) || 'auto-draft' === get_post_status( $post_id ) ) {
			return new \WP_Error(
				'rm_pl_not_saved',
				$not_saved_message,
				array( 'status' => 400 )
			);
		}
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return new \WP_Error(
				'rm_pl_no_acf',
				__( 'Advanced Custom Fields is required.', 'rm-audio-playlist' ),
				array( 'status' => 500 )
			);
		}
		return null;
	}
}

new Rest();
