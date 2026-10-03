<?php

/**
 * Plugin paths, version, textdomain, and block category.
 *
 * Initialized once from the main plugin file via {@see Config::init()}.
 *
 * @package Rm_Audio_Playlist
 */

declare(strict_types=1);

namespace Rm_Audio_Playlist;

/**
 * Plugin configuration.
 */
final class Config {

	private static ?self $instance = null;

	private string $version;

	private string $file;

	private string $dir;

	private string $url;

	private string $textdomain;

	private string $block_category;

	/**
	 * @var callable(): string
	 */
	private $block_category_title;

	/**
	 * @param array{
	 *   version: string,
	 *   file: string,
	 *   dir: string,
	 *   url: string,
	 *   textdomain: string,
	 *   block_category: string,
	 *   block_category_title: callable(): string
	 * } $args
	 */
	private function __construct(array $args) {
		$this->version              = $args['version'];
		$this->file                 = $args['file'];
		$this->dir                  = $args['dir'];
		$this->url                  = $args['url'];
		$this->textdomain           = $args['textdomain'];
		$this->block_category       = $args['block_category'];
		$this->block_category_title = $args['block_category_title'];
	}

	/**
	 * @param array{
	 *   version: string,
	 *   file: string,
	 *   dir: string,
	 *   url: string,
	 *   textdomain: string,
	 *   block_category: string,
	 *   block_category_title: callable(): string
	 * } $args
	 */
	public static function init(array $args): self {
		if (null !== self::$instance) {
			throw new \LogicException('Rm_Audio_Playlist\\Config already initialized.');
		}
		self::$instance = new self($args);
		return self::$instance;
	}

	public static function get(): self {
		if (null === self::$instance) {
			throw new \LogicException('Rm_Audio_Playlist\\Config not initialized.');
		}
		return self::$instance;
	}

	public function version(): string {
		return $this->version;
	}

	public function file(): string {
		return $this->file;
	}

	public function dir(): string {
		return $this->dir;
	}

	public function url(): string {
		return $this->url;
	}

	public function textdomain(): string {
		return $this->textdomain;
	}

	public function block_category(): string {
		return $this->block_category;
	}

	public function block_category_title(): string {
		return (string) ($this->block_category_title)();
	}
}
