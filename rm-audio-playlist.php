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

$rm_audio_playlist_dir = plugin_dir_path( __FILE__ );

require_once $rm_audio_playlist_dir . 'classes/class.config.php';

\Rm_Audio_Playlist\Config::init(
	array(
		'version'              => '1.4.0',
		'file'                 => __FILE__,
		'dir'                  => $rm_audio_playlist_dir,
		'url'                  => plugin_dir_url( __FILE__ ),
		'textdomain'           => 'rm-audio-playlist',
		'block_category'       => 'rm-audio-playlist',
		'block_category_title' => static function (): string {
			return __( 'RM Audio Playlist', 'rm-audio-playlist' );
		},
	)
);

$config = \Rm_Audio_Playlist\Config::get();

foreach ( glob( $config->dir() . 'classes/class.*.php' ) as $filename ) {
	if ( 'class.config.php' === basename( $filename ) ) {
		continue;
	}
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
