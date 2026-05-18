<?php

/**
 * Admin scripts (ACF edit screen).
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
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
		add_action('rest_api_init', array(self::class, 'register_rest_routes'));
		add_action('acf/input/admin_enqueue_scripts', array(self::class, 'enqueue_acf_scripts'));
		add_action('acf/save_post', array(self::class, 'fill_empty_track_titles_on_save'), 20);
		add_action(
			'acf/render_field/key=' . RM_Audio_Playlist_Acf::SHORTCODE_PANEL_FIELD_KEY,
			array(self::class, 'render_shortcode_panel'),
			1
		);
		add_action(
			'acf/render_field/key=' . RM_Audio_Playlist_Acf::CLEAR_TRACKS_DESC_FIELD_KEY,
			array(self::class, 'render_clear_tracks_description'),
			1
		);
		add_action(
			'acf/render_field/key=' . RM_Audio_Playlist_Acf::CLEAR_TRACKS_ACTION_FIELD_KEY,
			array(self::class, 'render_clear_tracks_action'),
			1
		);
		add_action(
			'acf/render_field/key=' . RM_Audio_Playlist_Acf::DOWNLOAD_ALL_FIELD_KEY,
			array(self::class, 'render_download_all_toggle'),
			1
		);
	}

	/**
	 * REST: clear playlist track files and repeater rows.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'rm-audio-playlist/v1',
			'/playlists/(?P<id>\d+)/clear-tracks',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array(self::class, 'rest_clear_playlist_tracks'),
				'permission_callback' => array(self::class, 'rest_can_edit_playlist'),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ($value): bool {
							return is_numeric($value) && (int) $value > 0;
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
				'callback'            => array(self::class, 'rest_set_downloadable_all'),
				'permission_callback' => array(self::class, 'rest_can_edit_playlist'),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ($value): bool {
							return is_numeric($value) && (int) $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public static function rest_can_edit_playlist(\WP_REST_Request $request): bool {
		$post_id = (int) $request['id'];
		if ($post_id <= 0) {
			return false;
		}
		if (RM_Audio_Playlist_Cpt::POST_TYPE !== get_post_type($post_id)) {
			return false;
		}
		return current_user_can('edit_post', $post_id);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_clear_playlist_tracks(\WP_REST_Request $request) {
		$post_id = (int) $request['id'];
		if (wp_is_post_autosave($post_id) || 'auto-draft' === get_post_status($post_id)) {
			return new \WP_Error(
				'rm_pl_not_saved',
				__('Save the playlist before removing tracks.', 'rm-audio-playlist'),
				array('status' => 400)
			);
		}
		if (! function_exists('get_field') || ! function_exists('update_field')) {
			return new \WP_Error(
				'rm_pl_no_acf',
				__('Advanced Custom Fields is required.', 'rm-audio-playlist'),
				array('status' => 500)
			);
		}

		$result = self::clear_playlist_tracks($post_id);
		return new \WP_REST_Response($result, 200);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_set_downloadable_all(\WP_REST_Request $request) {
		$post_id = (int) $request['id'];
		if (wp_is_post_autosave($post_id) || 'auto-draft' === get_post_status($post_id)) {
			return new \WP_Error(
				'rm_pl_not_saved',
				__('Save the playlist before changing download settings.', 'rm-audio-playlist'),
				array('status' => 400)
			);
		}
		if (! function_exists('get_field') || ! function_exists('update_field')) {
			return new \WP_Error(
				'rm_pl_no_acf',
				__('Advanced Custom Fields is required.', 'rm-audio-playlist'),
				array('status' => 500)
			);
		}

		$params = $request->get_json_params();
		if (! is_array($params) || ! array_key_exists('downloadable', $params)) {
			return new \WP_Error(
				'rm_pl_invalid_request',
				__('Missing downloadable flag.', 'rm-audio-playlist'),
				array('status' => 400)
			);
		}

		$downloadable = (bool) $params['downloadable'];
		$result       = self::set_playlist_downloadable_all($post_id, $downloadable);
		return new \WP_REST_Response($result, 200);
	}

	/**
	 * Set Allow download on every track row that has an MP3.
	 *
	 * @return array{updated: int, downloadable: bool}
	 */
	public static function set_playlist_downloadable_all(int $post_id, bool $downloadable): array {
		$rows = get_field(RM_Audio_Playlist_Acf::REPEATER, $post_id);
		if (! is_array($rows)) {
			$rows = array();
		}

		$value   = $downloadable ? 1 : 0;
		$updated = 0;

		foreach ($rows as $index => $row) {
			if (! is_array($row)) {
				continue;
			}
			$file_id = self::attachment_id_from_repeater_file_value($row[RM_Audio_Playlist_Acf::FILE_KEY] ?? null);
			if ($file_id <= 0) {
				continue;
			}
			$rows[$index][RM_Audio_Playlist_Acf::DOWNLOADABLE_KEY] = $value;
			++$updated;
		}

		if ($updated > 0) {
			update_field(RM_Audio_Playlist_Acf::REPEATER, $rows, $post_id);
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
	private static function playlist_downloadable_bulk_state(int $post_id): string {
		$rows = get_field(RM_Audio_Playlist_Acf::REPEATER, $post_id);
		if (! is_array($rows) || $rows === array()) {
			return 'empty';
		}

		$with_file = 0;
		$on        = 0;

		foreach ($rows as $row) {
			if (! is_array($row)) {
				continue;
			}
			$file_id = self::attachment_id_from_repeater_file_value($row[RM_Audio_Playlist_Acf::FILE_KEY] ?? null);
			if ($file_id <= 0) {
				continue;
			}
			++$with_file;
			if (! empty($row[RM_Audio_Playlist_Acf::DOWNLOADABLE_KEY])) {
				++$on;
			}
		}

		if ($with_file === 0) {
			return 'empty';
		}
		if ($on === 0) {
			return 'none';
		}
		if ($on === $with_file) {
			return 'all';
		}
		return 'mixed';
	}

	/**
	 * Delete playlist-scoped MP3 attachments and empty the tracks repeater.
	 *
	 * @return array{deleted: int[], skipped: int[], errors: string[]}
	 */
	public static function clear_playlist_tracks(int $post_id): array {
		$deleted = array();
		$skipped = array();
		$errors  = array();

		$rows = get_field(RM_Audio_Playlist_Acf::REPEATER, $post_id);
		if (! is_array($rows)) {
			$rows = array();
		}

		$file_ids = array();
		foreach ($rows as $row) {
			if (! is_array($row)) {
				continue;
			}
			$file_id = self::attachment_id_from_repeater_file_value($row[RM_Audio_Playlist_Acf::FILE_KEY] ?? null);
			if ($file_id > 0) {
				$file_ids[$file_id] = true;
			}
		}

		foreach (array_keys($file_ids) as $file_id) {
			if (! self::attachment_is_in_playlist_upload_dir($file_id, $post_id)) {
				$skipped[] = $file_id;
				continue;
			}
			$ok = wp_delete_attachment($file_id, true);
			if ($ok) {
				$deleted[] = $file_id;
			} else {
				$errors[] = sprintf(
					/* translators: %d: attachment ID */
					__('Could not delete attachment %d.', 'rm-audio-playlist'),
					$file_id
				);
			}
		}

		update_field(RM_Audio_Playlist_Acf::REPEATER, array(), $post_id);

		return array(
			'deleted' => $deleted,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * True when the attachment file lives under uploads/rm-audio-playlist/{playlist ID}/.
	 */
	private static function attachment_is_in_playlist_upload_dir(int $file_id, int $playlist_id): bool {
		$path = get_attached_file($file_id);
		if (! is_string($path) || $path === '') {
			return false;
		}
		$uploads = wp_upload_dir();
		if (! empty($uploads['error'])) {
			return false;
		}
		$prefix = trailingslashit(wp_normalize_path($uploads['basedir']))
			. RM_Audio_Playlist_Upload_Dir::SUBDIR
			. '/'
			. (string) $playlist_id
			. '/';
		return str_starts_with(wp_normalize_path($path), $prefix);
	}

	/**
	 * @param mixed $file_raw ACF file subfield value.
	 */
	private static function attachment_id_from_repeater_file_value($file_raw): int {
		if (is_array($file_raw) && isset($file_raw['ID'])) {
			return (int) $file_raw['ID'];
		}
		if (is_numeric($file_raw)) {
			return (int) $file_raw;
		}
		return 0;
	}

	/**
	 * After ACF saves, set empty track titles from embedded ID3 tags when readable, else the file stem.
	 *
	 * @param int|string $post_id Post ID or 'options'.
	 */
	public static function fill_empty_track_titles_on_save($post_id): void {
		if (! is_numeric($post_id)) {
			return;
		}
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return;
		}
		if (RM_Audio_Playlist_Cpt::POST_TYPE !== get_post_type($post_id)) {
			return;
		}
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}
		if (! function_exists('get_field') || ! function_exists('update_sub_field')) {
			return;
		}

		static $running = false;
		if ($running) {
			return;
		}

		$rows = get_field(RM_Audio_Playlist_Acf::REPEATER, $post_id);
		if (! is_array($rows) || $rows === array()) {
			return;
		}

		$running = true;
		try {
			$row_num = 1;
			foreach ($rows as $row) {
				if (! is_array($row)) {
					++$row_num;
					continue;
				}
				$file_id = self::attachment_id_from_repeater_file_value($row[RM_Audio_Playlist_Acf::FILE_KEY] ?? null);
				$title = isset($row[RM_Audio_Playlist_Acf::TITLE_KEY]) ? trim((string) $row[RM_Audio_Playlist_Acf::TITLE_KEY]) : '';
				if ($file_id <= 0 || '' !== $title) {
					++$row_num;
					continue;
				}
				$suggested = self::suggested_track_title_for_attachment($file_id);
				if ('' === $suggested) {
					++$row_num;
					continue;
				}
				update_sub_field(
					array(
						RM_Audio_Playlist_Acf::REPEATER,
						$row_num,
						RM_Audio_Playlist_Acf::TITLE_KEY,
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
	private static function suggested_track_title_for_attachment(int $file_id): string {
		$from_tags = self::track_title_from_id3_tags($file_id);
		if ('' !== $from_tags) {
			return $from_tags;
		}
		return self::attachment_filename_stem($file_id);
	}

	/**
	 * Build a display title from WordPress audio metadata (getID3): "Artist - Title", title-only, or artist-only.
	 *
	 * @return string Empty if no usable tag text (caller falls back to filename stem).
	 */
	private static function track_title_from_id3_tags(int $file_id): string {
		if (! function_exists('wp_read_audio_metadata')) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$path = get_attached_file($file_id);
		if (! is_string($path) || $path === '' || ! is_readable($path)) {
			return '';
		}
		$meta = wp_read_audio_metadata($path);
		if (! is_array($meta)) {
			return '';
		}
		$artist = '';
		if (isset($meta['artist']) && is_string($meta['artist'])) {
			$artist = self::normalize_track_label_piece($meta['artist']);
		}
		if ('' === $artist && isset($meta['band']) && is_string($meta['band'])) {
			$artist = self::normalize_track_label_piece($meta['band']);
		}
		$title = isset($meta['title']) && is_string($meta['title'])
			? self::normalize_track_label_piece($meta['title'])
			: '';
		if ('' !== $artist && '' !== $title) {
			return $artist . ' - ' . $title;
		}
		if ('' !== $title) {
			return $title;
		}
		if ('' !== $artist) {
			return $artist;
		}
		return '';
	}

	/**
	 * Single-line text from tag values for storage/display.
	 */
	private static function normalize_track_label_piece(string $raw): string {
		$s = wp_strip_all_tags($raw);
		$s = wp_specialchars_decode($s, ENT_QUOTES);
		$s = preg_replace('/\s+/u', ' ', $s);
		if (! is_string($s)) {
			return '';
		}
		return trim($s);
	}

	/**
	 * Filename without extension from attachment (separators → spaces), or attachment post title.
	 */
	private static function attachment_filename_stem(int $file_id): string {
		$path = get_attached_file($file_id);
		if (is_string($path) && $path !== '') {
			$base = wp_basename($path);
			$stem = preg_replace('/\.[^.]+\z/', '', $base);
			if (! is_string($stem)) {
				return '';
			}
			return self::normalize_track_title_from_filename_stem($stem);
		}
		$post = get_post($file_id);
		if ($post instanceof \WP_Post && $post->post_title !== '') {
			return trim((string) $post->post_title);
		}
		return '';
	}

	/**
	 * Turn filename stem into a readable title: separators → spaces, collapse whitespace.
	 */
	private static function normalize_track_title_from_filename_stem(string $stem): string {
		// Hyphens, Unicode dashes, underscores, dots, pipe, middle dot, bullets, plus (common in tags).
		$stem = preg_replace('/[\s_.|·•+]+|\p{Pd}+/u', ' ', $stem);
		if (! is_string($stem)) {
			return '';
		}
		$stem = preg_replace('/\s+/', ' ', $stem);
		return trim($stem);
	}

	/**
	 * Output copyable shortcode (first field in the group) when the playlist is published.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 */
	public static function render_shortcode_panel(array $field): void {
		unset($field);
		global $post;
		if (! $post instanceof \WP_Post || RM_Audio_Playlist_Cpt::POST_TYPE !== $post->post_type) {
			return;
		}
		if (! in_array($post->post_status, array('publish', 'future'), true)) {
?>
			<p class="description" style="margin: 0;">
				<?php esc_html_e('Publish or schedule this playlist to copy the embed shortcode.', 'rm-audio-playlist'); ?>
			</p>
		<?php
			return;
		}
		$post_id = (int) $post->ID;
		if ($post_id <= 0) {
			return;
		}

		$code = sprintf('[rm_audio_playlist id="%d"]', $post_id);
		?>
		<div class="rm-pl-shortcode-panel notice notice-info inline" style="margin: 0 0 16px; padding: 10px 12px;">
			<p class="description" style="margin: 0 0 8px;">
				<?php esc_html_e('Copy this into a page, post, or HTML block to embed this playlist.', 'rm-audio-playlist'); ?>
			</p>
			<label class="screen-reader-text" for="rm-pl-shortcode-copy"><?php esc_html_e('Playlist shortcode', 'rm-audio-playlist'); ?></label>
			<input
				id="rm-pl-shortcode-copy"
				type="text"
				readonly
				class="large-text code"
				style="width: 100%; max-width: 40rem; font-size: 13px;"
				value="<?php echo esc_attr($code); ?>" />
		</div>
	<?php
	}

	/**
	 * @return array{post_id: int, can_manage: bool, bulk_dl: string, has_tracks: bool}|null
	 */
	private static function playlist_toolbar_context(): ?array {
		global $post;
		if (! $post instanceof \WP_Post || RM_Audio_Playlist_Cpt::POST_TYPE !== $post->post_type) {
			return null;
		}
		$post_id = (int) $post->ID;
		if ($post_id <= 0 || ! current_user_can('edit_post', $post_id)) {
			return null;
		}
		$bulk_dl = self::playlist_downloadable_bulk_state($post_id);
		return array(
			'post_id'    => $post_id,
			'can_manage' => ! wp_is_post_autosave($post_id) && 'auto-draft' !== $post->post_status,
			'bulk_dl'    => $bulk_dl,
			'has_tracks' => 'empty' !== $bulk_dl,
		);
	}

	/**
	 * Reset tracks — description column.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 */
	public static function render_clear_tracks_description(array $field): void {
		unset($field);
		if (null === self::playlist_toolbar_context()) {
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
	public static function render_clear_tracks_action(array $field): void {
		unset($field);
		$ctx = self::playlist_toolbar_context();
		if (null === $ctx) {
			return;
		}
	?>
		<div class="rm-pl-toolbar-col__inner">
			<?php if ($ctx['can_manage']) : ?>
				<button type="button" class="button" id="rm-pl-clear-tracks">
					<?php esc_html_e('Remove all MP3s…', 'rm-audio-playlist'); ?>
				</button>
				<span id="rm-pl-clear-tracks-status" class="description rm-pl-toolbar-col__status" hidden></span>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e('Save the playlist before you can remove tracks.', 'rm-audio-playlist'); ?>
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
	public static function render_download_all_toggle(array $field): void {
		unset($field);
		$ctx = self::playlist_toolbar_context();
		if (null === $ctx) {
			return;
		}
	?>
		<div class="rm-pl-toolbar-col__inner">
			<?php if ($ctx['can_manage']) : ?>
				<label class="rm-pl-tracks-toolbar__toggle" for="rm-pl-download-all">
					<input
						type="checkbox"
						id="rm-pl-download-all"
						<?php checked('all' === $ctx['bulk_dl']); ?>
						<?php disabled(! $ctx['has_tracks']); ?>
						data-bulk-state="<?php echo esc_attr($ctx['bulk_dl']); ?>" />
					<span class="rm-pl-tracks-toolbar__toggle-ui" aria-hidden="true"></span>
				</label>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e('Save the playlist before you can change download settings.', 'rm-audio-playlist'); ?>
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
		if (! $screen || RM_Audio_Playlist_Cpt::POST_TYPE !== $screen->post_type) {
			return;
		}

		wp_enqueue_style(
			'rm-audio-playlist-admin',
			RM_AUDIO_PLAYLIST_URL . 'assets/css/rm-audio-playlist-admin.css',
			array(),
			RM_AUDIO_PLAYLIST_VERSION
		);

		wp_enqueue_script(
			'rm-audio-playlist-admin',
			RM_AUDIO_PLAYLIST_URL . 'assets/js/rm-audio-playlist-admin.js',
			array(),
			RM_AUDIO_PLAYLIST_VERSION,
			true
		);

		global $post;
		$localize = array(
			'nonce' => wp_create_nonce('wp_rest'),
		);
		if ($post instanceof \WP_Post && RM_Audio_Playlist_Cpt::POST_TYPE === $post->post_type) {
			$post_id = (int) $post->ID;
			if (
				$post_id > 0
				&& current_user_can('edit_post', $post_id)
				&& ! wp_is_post_autosave($post_id)
				&& 'auto-draft' !== $post->post_status
			) {
				$localize['restClearUrl']        = rest_url('rm-audio-playlist/v1/playlists/' . $post_id . '/clear-tracks');
				$localize['restDownloadAllUrl'] = rest_url('rm-audio-playlist/v1/playlists/' . $post_id . '/set-downloadable-all');
			}
		}
		$localize['confirmClear'] = __(
			'Permanently delete all MP3 files stored for this playlist and clear every track row? This cannot be undone. Playlist artwork is kept.',
			'rm-audio-playlist'
		);
		$localize['clearing']          = __('Removing tracks…', 'rm-audio-playlist');
		$localize['clearFailed']       = __('Could not remove tracks. Try again or check your connection.', 'rm-audio-playlist');
		$localize['downloadAllFailed'] = __('Could not update download settings. Try again or check your connection.', 'rm-audio-playlist');
		$localize['downloadAllSaving'] = __('Updating download settings…', 'rm-audio-playlist');

		wp_localize_script('rm-audio-playlist-admin', 'rmAudioPlaylistAdmin', $localize);
	}
}
