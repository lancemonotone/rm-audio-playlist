<?php
/**
 * Plugin Name:       RM Audio Playlist
 * Description:         Admin ACF-backed MP3 playlists and a public player with play order, speed, skip, repeat, shuffle, and keyboard support.
 * Version:             1.2.7
 * Requires at least:   6.0
 * Requires PHP:        7.4
 * Author:              Rusmiller
 * Text Domain:         rm-audio-playlist
 *
 * @package rm-audio-playlist
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RM_AUDIO_PLAYLIST_VERSION', '1.2.7' );
define( 'RM_AUDIO_PLAYLIST_FILE', __FILE__ );
define( 'RM_AUDIO_PLAYLIST_DIR', plugin_dir_path( __FILE__ ) );
define( 'RM_AUDIO_PLAYLIST_URL', plugin_dir_url( __FILE__ ) );

require_once RM_AUDIO_PLAYLIST_DIR . 'includes/class-rm-audio-playlist-mime.php';
require_once RM_AUDIO_PLAYLIST_DIR . 'includes/class-rm-audio-playlist-cpt.php';
require_once RM_AUDIO_PLAYLIST_DIR . 'includes/class-rm-audio-playlist-acf.php';
require_once RM_AUDIO_PLAYLIST_DIR . 'includes/class-rm-audio-playlist-frontend.php';
require_once RM_AUDIO_PLAYLIST_DIR . 'includes/class-rm-audio-playlist-admin.php';

add_filter( 'upload_mimes', array( 'RM_Audio_Playlist_Mime', 'allow_mp3' ) );
add_filter( 'wp_check_filetype_and_ext', array( 'RM_Audio_Playlist_Mime', 'fix_mp3_check' ), 10, 5 );

add_action( 'init', array( 'RM_Audio_Playlist_Cpt', 'register' ) );
add_action( 'admin_notices', array( 'RM_Audio_Playlist_Cpt', 'admin_notice_acf' ) );
add_action( 'acf/init', array( 'RM_Audio_Playlist_Acf', 'register' ) );

RM_Audio_Playlist_Frontend::init();
RM_Audio_Playlist_Admin::init();

/**
 * Fires on plugin activation: register CPT and flush rewrites.
 */
function rm_audio_playlist_activate(): void {
	RM_Audio_Playlist_Cpt::register();
	flush_rewrite_rules( true );
}

/**
 * Fires on plugin deactivation.
 */
function rm_audio_playlist_deactivate(): void {
	flush_rewrite_rules( true );
}

register_activation_hook( __FILE__, 'rm_audio_playlist_activate' );
register_deactivation_hook( __FILE__, 'rm_audio_playlist_deactivate' );
