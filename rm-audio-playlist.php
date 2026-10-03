<?php
/**
 * Plugin Name:       RM Audio Playlist
 * Description:       Admin ACF-backed MP3 playlists and a block editor player with play order, speed, skip, repeat, shuffle, and keyboard support.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Rusmiller
 * Text Domain:       rm-audio-playlist
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RM_AUDIO_PLAYLIST_VERSION', '1.4.0' );
define( 'RM_AUDIO_PLAYLIST_FILE', __FILE__ );
define( 'RM_AUDIO_PLAYLIST_DIR', plugin_dir_path( __FILE__ ) );
define( 'RM_AUDIO_PLAYLIST_URL', plugin_dir_url( __FILE__ ) );

foreach ( glob( RM_AUDIO_PLAYLIST_DIR . 'classes/class.*.php' ) as $filename ) {
	require_once $filename;
}

/**
 * Activation: block packages (and other listeners) handle feature setup via hook.
 */
function rm_audio_playlist_activate(): void {
	do_action( 'rm_audio_playlist_activate' );
	flush_rewrite_rules( true );
}

/**
 * Deactivation: flush rewrites.
 */
function rm_audio_playlist_deactivate(): void {
	flush_rewrite_rules( true );
}

register_activation_hook( __FILE__, 'rm_audio_playlist_activate' );
register_deactivation_hook( __FILE__, 'rm_audio_playlist_deactivate' );
