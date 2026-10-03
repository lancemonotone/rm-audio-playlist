<?php
/**
 * ACF edit-screen toolbar UI + admin script localize for playlists.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * CPT field chrome only; enqueue and domain state via hooks/filters.
 */
final class Admin_Acf_Ui {

	public function __construct() {
		add_action( 'acf/input/admin_enqueue_scripts', array( self::class, 'enqueue_acf_scripts' ) );
		add_action(
			'acf/render_field/key=' . Constants::CLEAR_TRACKS_DESC_FIELD_KEY,
			array( self::class, 'render_clear_tracks_description' ),
			1
		);
		add_action(
			'acf/render_field/key=' . Constants::CLEAR_TRACKS_ACTION_FIELD_KEY,
			array( self::class, 'render_clear_tracks_action' ),
			1
		);
		add_action(
			'acf/render_field/key=' . Constants::DOWNLOAD_ALL_FIELD_KEY,
			array( self::class, 'render_download_all_toggle' ),
			1
		);
	}

	/**
	 * @return array{post_id: int, can_manage: bool, bulk_dl: string, has_tracks: bool}|null
	 */
	private static function playlist_toolbar_context(): ?array {
		global $post;
		if ( ! $post instanceof \WP_Post || Cpt::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$post_id = (int) $post->ID;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}
		$bulk_dl = apply_filters( 'rm_audio_playlist_downloadable_bulk_state', null, $post_id );
		if ( ! is_string( $bulk_dl ) ) {
			$bulk_dl = 'empty';
		}
		return array(
			'post_id'    => $post_id,
			'can_manage' => ! wp_is_post_autosave( $post_id ) && 'auto-draft' !== $post->post_status,
			'bulk_dl'    => $bulk_dl,
			'has_tracks' => 'empty' !== $bulk_dl,
		);
	}

	/**
	 * Reset tracks — description column.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 */
	public static function render_clear_tracks_description( array $field ): void {
		unset( $field );
		if ( null === self::playlist_toolbar_context() ) {
			return;
		}
		?>
		<div class="rm-pl-toolbar-col__inner">
			<p class="description rm-pl-toolbar-col__desc">
				<?php
				esc_html_e(
					'Permanently deletes MP3 files stored for this playlist (under this playlist’s upload folder), then clears all track rows so you can upload new files. Playlist title and artwork are not changed. Files chosen from elsewhere in the Media Library are skipped, not deleted.',
					'rm-audio-playlist'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Reset tracks — remove MP3s column.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 */
	public static function render_clear_tracks_action( array $field ): void {
		unset( $field );
		$ctx = self::playlist_toolbar_context();
		if ( null === $ctx ) {
			return;
		}
		?>
		<div class="rm-pl-toolbar-col__inner">
			<?php if ( $ctx['can_manage'] ) : ?>
				<button type="button" class="button" id="rm-pl-clear-tracks">
					<?php esc_html_e( 'Remove all MP3s…', 'rm-audio-playlist' ); ?>
				</button>
				<span id="rm-pl-clear-tracks-status" class="description rm-pl-toolbar-col__status" hidden></span>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Save the playlist before you can remove tracks.', 'rm-audio-playlist' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Reset tracks — bulk allow download column.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 */
	public static function render_download_all_toggle( array $field ): void {
		unset( $field );
		$ctx = self::playlist_toolbar_context();
		if ( null === $ctx ) {
			return;
		}
		?>
		<div class="rm-pl-toolbar-col__inner">
			<?php if ( $ctx['can_manage'] ) : ?>
				<label class="rm-pl-tracks-toolbar__toggle" for="rm-pl-download-all">
					<input
						type="checkbox"
						id="rm-pl-download-all"
						<?php checked( 'all' === $ctx['bulk_dl'] ); ?>
						<?php disabled( ! $ctx['has_tracks'] ); ?>
						data-bulk-state="<?php echo esc_attr( $ctx['bulk_dl'] ); ?>" />
					<span class="rm-pl-tracks-toolbar__toggle-ui" aria-hidden="true"></span>
				</label>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Save the playlist before you can change download settings.', 'rm-audio-playlist' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register script for playlist edit screen (ACF track fields).
	 */
	public static function enqueue_acf_scripts(): void {
		$screen = get_current_screen();
		if ( ! $screen || Cpt::POST_TYPE !== $screen->post_type ) {
			return;
		}

		do_action( 'rm_audio_playlist_enqueue_admin' );

		global $post;
		$localize = array(
			'nonce' => wp_create_nonce( 'wp_rest' ),
		);
		if ( $post instanceof \WP_Post && Cpt::POST_TYPE === $post->post_type ) {
			$post_id = (int) $post->ID;
			if (
				$post_id > 0
				&& current_user_can( 'edit_post', $post_id )
				&& ! wp_is_post_autosave( $post_id )
				&& 'auto-draft' !== $post->post_status
			) {
				$localize['restClearUrl']       = rest_url( 'rm-audio-playlist/v1/playlists/' . $post_id . '/clear-tracks' );
				$localize['restDownloadAllUrl'] = rest_url( 'rm-audio-playlist/v1/playlists/' . $post_id . '/set-downloadable-all' );
			}
		}
		$localize['confirmClear'] = __(
			'Permanently delete all MP3 files stored for this playlist and clear every track row? This cannot be undone. Playlist artwork is kept.',
			'rm-audio-playlist'
		);
		$localize['clearing']          = __( 'Removing tracks…', 'rm-audio-playlist' );
		$localize['clearFailed']       = __( 'Could not remove tracks. Try again or check your connection.', 'rm-audio-playlist' );
		$localize['downloadAllFailed'] = __( 'Could not update download settings. Try again or check your connection.', 'rm-audio-playlist' );
		$localize['downloadAllSaving'] = __( 'Updating download settings…', 'rm-audio-playlist' );

		wp_localize_script( Constants::HANDLE_ADMIN, 'rmAudioPlaylistAdmin', $localize );
	}
}

new Admin_Acf_Ui();
