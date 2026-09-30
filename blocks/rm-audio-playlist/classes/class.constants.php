<?php

/**
 * Playlist slug, admin handle, and ACF field name / key identifiers.
 *
 * Field group defs live in this block's acf-json/ and fields.json (loaded by Acf).
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Shared string constants for slug, enqueue handles, and ACF fields.
 */
final class Constants {

	public const SLUG = 'rm-audio-playlist';

	public const HANDLE_ADMIN = 'rm-audio-playlist-admin';

	/** CPT field names (match acf-json group). */
	public const REPEATER         = 'rm_pl_tracks';
	public const FILE_KEY         = 'rm_pl_file';
	public const TITLE_KEY        = 'rm_pl_track_title';
	public const DOWNLOADABLE_KEY = 'rm_pl_downloadable';
	public const ARTWORK_KEY      = 'rm_pl_artwork';

	/** ACF message fields: UI only (rendered via acf/render_field). */
	public const CLEAR_TRACKS_DESC_FIELD_KEY   = 'field_rm_pl_clear_tracks_desc';
	public const CLEAR_TRACKS_ACTION_FIELD_KEY = 'field_rm_pl_clear_tracks_action';
	public const DOWNLOAD_ALL_FIELD_KEY        = 'field_rm_pl_download_all';
}

new Constants();
